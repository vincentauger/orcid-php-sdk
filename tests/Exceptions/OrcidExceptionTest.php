<?php

declare(strict_types=1);

use Saloon\Enums\Method;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Tests\Fixtures\OrcidFixture;
use Tests\Support\RawRequest;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;
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

/**
 * Send a request answered by the given mock and return what sendOrFail() throws
 */
function orcidFailure(MockResponse|OrcidFixture $mock): OrcidException
{
    $client = Orcid::member('token')->withMockClient(new MockClient([$mock]));

    try {
        $client->sendOrFail(new RawRequest);
    } catch (OrcidException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected an OrcidException');
}

it('maps a bad payload to a validation exception with ORCID error details', function (): void {
    $exception = orcidFailure(new OrcidFixture('a1/e4-post-no-disambiguated-org'));

    expect($exception)->toBeInstanceOf(OrcidValidationException::class)
        ->and($exception->errorCode)->toBe(9045)
        ->and($exception->developerMessage)->toBe('400 Bad request: disambiguated-organization must be present and valid')
        ->and($exception->userMessage)->toBe('No valid disambiguated-organization is included in the data.')
        ->and($exception->getMessage())->toBe('ORCID request failed with status 400 (error 9045): 400 Bad request: disambiguated-organization must be present and valid')
        ->and($exception->isRetryable())->toBeFalse();
});

it('maps a bad or revoked token (OAuth error body) to an auth exception', function (): void {
    $exception = orcidFailure(new OrcidFixture('a1/e8-invalid-token'));

    expect($exception)->toBeInstanceOf(OrcidAuthException::class)
        ->and($exception->oauthError)->toBe('invalid_token')
        ->and($exception->errorCode)->toBeNull()
        ->and($exception->isRetryable())->toBeFalse();
});

it('tells "not the source" apart from "missing scope" on 403', function (): void {
    $notSource = orcidFailure(new OrcidFixture('a1/e7-put-other-source'));
    $missingScope = orcidFailure(new OrcidFixture('a1/e9-write-without-scope'));

    expect($notSource)->toBeInstanceOf(OrcidForbiddenException::class)
        ->and($notSource->isNotSource())->toBeTrue()
        ->and($notSource->isMissingScope())->toBeFalse()
        ->and($missingScope)->toBeInstanceOf(OrcidForbiddenException::class)
        ->and($missingScope->isMissingScope())->toBeTrue()
        ->and($missingScope->isNotSource())->toBeFalse();
});

it('maps an unknown item to a not found exception', function (): void {
    $exception = orcidFailure(new OrcidFixture('a1/e6-put-unknown'));

    expect($exception)->toBeInstanceOf(OrcidNotFoundException::class)
        ->and($exception->errorCode)->toBe(9016);
});

it('marks rate limits and server errors as retryable', function (int $status, string $class): void {
    $exception = orcidFailure(MockResponse::make('<html>Bad gateway</html>', $status));

    expect($exception)->toBeInstanceOf($class)
        ->and($exception->isRetryable())->toBeTrue()
        ->and($exception->getMessage())->toBe("ORCID request failed with status {$status}");
})->with([
    [429, OrcidRateLimitException::class],
    [500, OrcidServerException::class],
    [503, OrcidServerException::class],
]);

it('uses the base request exception for other statuses', function (): void {
    $exception = orcidFailure(MockResponse::make([], 405));

    expect($exception::class)->toBe(OrcidRequestException::class)
        ->and($exception->isRetryable())->toBeFalse();
});

it('wraps connection failures in a retryable connection exception', function (): void {
    $client = Orcid::member('token')->withMockClient(new MockClient([
        RawRequest::class => fn (PendingRequest $pending) => throw new FatalRequestException(new Exception('cURL error 28: timed out'), $pending),
    ]));

    expect(fn (): Response => $client->sendOrFail(new RawRequest))
        ->toThrow(function (OrcidConnectionException $exception): void {
            expect($exception->isRetryable())->toBeTrue();
        });
});

it('keeps send() returning the raw response', function (): void {
    $client = Orcid::member('token')->withMockClient(new MockClient([new OrcidFixture('a1/e6-put-unknown')]));

    expect($client->send(new RawRequest)->status())->toBe(404);
});

it('refuses Member API requests on the Public API', function (): void {
    $request = new class extends Request implements RequiresMemberApi
    {
        protected Method $method = Method::GET;

        public function resolveEndpoint(): string
        {
            return '/';
        }
    };

    Orcid::public()->withMockClient(new MockClient([MockResponse::make()]))->send($request);
})->throws(LogicException::class, 'requires the Member API');

it('checks scopes before sending when the token scopes are known', function (): void {
    $request = new class extends Request implements RequiresScopes
    {
        protected Method $method = Method::POST;

        public function resolveEndpoint(): string
        {
            return '/';
        }

        public function requiredScopes(): array
        {
            return [Scope::ActivitiesUpdate];
        }
    };

    $mockClient = new MockClient(['*' => MockResponse::make([], 201)]);

    // unknown scopes: no check
    expect(Orcid::member('token')->withMockClient($mockClient)->send($request)->status())->toBe(201)
        // the token has the scope
        ->and(Orcid::member('token', scopes: Scope::completeAccess())->withMockClient($mockClient)->send($request)->status())->toBe(201);

    // the token lacks it: nothing is sent
    expect(fn (): Response => Orcid::member('token', scopes: [Scope::ReadLimited])->withMockClient($mockClient)->send($request))
        ->toThrow(MissingScopeException::class, 'requires the /activities/update scope(s)');

    $mockClient->assertSentCount(2);
});
