<?php

declare(strict_types=1);

use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Affiliations\AffiliationInput;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Data\Person\Person;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Enums\Environment;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Exceptions\OrcidAuthException;
use VincentAuger\OrcidSdk\Exceptions\OrcidConnectionException;
use VincentAuger\OrcidSdk\Exceptions\OrcidException;
use VincentAuger\OrcidSdk\Exceptions\OrcidForbiddenException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Exceptions\OrcidRateLimitException;
use VincentAuger\OrcidSdk\Exceptions\OrcidRequestException;
use VincentAuger\OrcidSdk\Exceptions\OrcidServerException;
use VincentAuger\OrcidSdk\Exceptions\OrcidValidationException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Affiliations\CreateAffiliation;
use VincentAuger\OrcidSdk\Requests\Affiliations\GetAffiliation;
use VincentAuger\OrcidSdk\Requests\OAuth\RequestClientCredentialsToken;
use VincentAuger\OrcidSdk\Requests\OAuth\RevokeToken;
use VincentAuger\OrcidSdk\Requests\Person\GetPerson;

/*
 * Errors recorded against the ORCID sandbox through the typed requests. Rate limits,
 * server and connection errors can't be produced on demand, so they are mocked.
 */

/**
 * Run a call and return the OrcidException it throws
 */
function orcidFailure(Closure $call): OrcidException
{
    try {
        $call();
    } catch (OrcidException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected an OrcidException');
}

function errorTestAffiliation(string $ror): AffiliationInput
{
    return new AffiliationInput(
        organization: new Organization(
            'Fisheries and Oceans Canada',
            new OrganizationAddress('Ottawa', 'CA'),
            DisambiguatedOrganization::ror($ror),
        ),
        departmentName: OrcidFixture::TEST_MARKER.' errors',
    );
}

/**
 * Public API client answering every request with the given mock
 */
function mockedPublicApi(MockResponse|Closure $mock): Orcid
{
    return Orcid::public(environment: Environment::Sandbox)->withMockClient(new MockClient([GetPerson::class => $mock]));
}

it('maps a bad payload to a validation exception with ORCID error details', function (): void {
    $client = $this->getMemberApiClient()->withMockClient(new MockClient([new OrcidFixture('errors/invalid-organization')]));

    $exception = orcidFailure(fn (): int => $client->record($this->orcidId())->affiliations()
        ->create(AffiliationType::Employment, errorTestAffiliation('https://ror.org/000000000')));

    expect($exception)->toBeInstanceOf(OrcidValidationException::class)
        ->and($exception->errorCode)->toBe(9045)
        ->and($exception->developerMessage)->toBe('400 Bad request: disambiguated-organization must be present and valid')
        ->and($exception->userMessage)->toBe('No valid disambiguated-organization is included in the data.')
        ->and($exception->getMessage())->toBe('ORCID request failed with status 400 (error 9045): 400 Bad request: disambiguated-organization must be present and valid')
        ->and($exception->isRetryable())->toBeFalse();
});

it('maps a bad or revoked token (OAuth error body) to an auth exception', function (): void {
    $client = Orcid::member('invalid-token', Environment::Sandbox)->withMockClient(new MockClient([new OrcidFixture('errors/invalid-token')]));

    $exception = orcidFailure(fn (): Person => $client->record($this->orcidId())->person());

    expect($exception)->toBeInstanceOf(OrcidAuthException::class)
        ->and($exception->oauthError)->toBe('invalid_token')
        ->and($exception->errorCode)->toBeNull()
        ->and($exception->isRetryable())->toBeFalse();
});

it('tells "not the source" apart from "missing scope" on 403', function (): void {
    // put-code 71553 is an employment the sandbox record owner added by hand
    $client = $this->getMemberApiClient()->withMockClient(new MockClient([new OrcidFixture('affiliations/update-other-source')]));
    $notSource = orcidFailure(fn () => $client->record($this->orcidId())->affiliations()
        ->update(AffiliationType::Employment, 71553, errorTestAffiliation('https://ror.org/02qa1x782')));

    // a throwaway /read-public token can't write, even on the Member API. To re-record, delete the whole folder.
    $mockClient = new MockClient([
        RequestClientCredentialsToken::class => new OrcidFixture('errors/missing-scope/client-credentials'),
        CreateAffiliation::class => new OrcidFixture('errors/missing-scope/create'),
        RevokeToken::class => new OrcidFixture('errors/missing-scope/revoke'),
    ]);
    $oauth = $this->getOAuthClient()->withMockClient($mockClient);
    $readPublic = $oauth->clientCredentials([Scope::ReadPublic])->accessToken;
    $client = Orcid::member($readPublic, Environment::Sandbox)->withMockClient($mockClient);
    $missingScope = orcidFailure(fn (): int => $client->record($this->orcidId())->affiliations()
        ->create(AffiliationType::Employment, errorTestAffiliation('https://ror.org/02qa1x782')));
    $oauth->revoke($readPublic);

    expect($notSource)->toBeInstanceOf(OrcidForbiddenException::class)
        ->and($notSource->isNotSource())->toBeTrue()
        ->and($notSource->isMissingScope())->toBeFalse()
        ->and($missingScope)->toBeInstanceOf(OrcidForbiddenException::class)
        ->and($missingScope->isMissingScope())->toBeTrue()
        ->and($missingScope->isNotSource())->toBeFalse();
});

it('maps an unknown item to a not found exception', function (): void {
    $client = $this->getMemberApiClient()->withMockClient(new MockClient([new OrcidFixture('errors/not-found')]));

    $exception = orcidFailure(fn () => $client->record($this->orcidId())->affiliations()->get(AffiliationType::Employment, 1));

    expect($exception)->toBeInstanceOf(OrcidNotFoundException::class)
        ->and($exception->errorCode)->toBe(9016);
});

it('keeps send() returning the raw response', function (): void {
    $client = $this->getMemberApiClient()->withMockClient(new MockClient([new OrcidFixture('errors/not-found')]));

    expect($client->send(new GetAffiliation($this->orcidId(), AffiliationType::Employment, 1))->status())->toBe(404);
});

it('marks rate limits and server errors as retryable', function (int $status, string $class): void {
    $client = mockedPublicApi(MockResponse::make('<html>Bad gateway</html>', $status));

    $exception = orcidFailure(fn (): Person => $client->record($this->orcidId())->person());

    expect($exception)->toBeInstanceOf($class)
        ->and($exception->isRetryable())->toBeTrue()
        ->and($exception->getMessage())->toBe("ORCID request failed with status {$status}");
})->with([
    [429, OrcidRateLimitException::class],
    [500, OrcidServerException::class],
    [503, OrcidServerException::class],
]);

it('uses the base request exception for other statuses', function (): void {
    $client = mockedPublicApi(MockResponse::make([], 405));

    $exception = orcidFailure(fn (): Person => $client->record($this->orcidId())->person());

    expect($exception::class)->toBe(OrcidRequestException::class)
        ->and($exception->isRetryable())->toBeFalse();
});

it('wraps connection failures in a retryable connection exception', function (): void {
    $client = mockedPublicApi(fn (PendingRequest $pending) => throw new FatalRequestException(new Exception('cURL error 28: timed out'), $pending));

    $exception = orcidFailure(fn (): Person => $client->record($this->orcidId())->person());

    expect($exception)->toBeInstanceOf(OrcidConnectionException::class)
        ->and($exception->isRetryable())->toBeTrue();
});
