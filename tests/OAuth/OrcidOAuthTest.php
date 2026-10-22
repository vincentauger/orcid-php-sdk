<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use VincentAuger\OrcidSdk\Data\OrcidToken;
use VincentAuger\OrcidSdk\Enums\Environment;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Exceptions\OrcidValidationException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\OrcidOAuth;
use VincentAuger\OrcidSdk\Requests\OAuth\ExchangeAuthorizationCode;
use VincentAuger\OrcidSdk\Requests\OAuth\RequestClientCredentialsToken;

/**
 * Token response as documented by ORCID for the authorization code flow
 */
function threeLeggedTokenResponse(): MockResponse
{
    return MockResponse::make([
        'access_token' => 'f5af9f51-07e6-4332-8f1a-c0c11c1e3728',
        'token_type' => 'bearer',
        'refresh_token' => 'f725f747-3a65-49f6-a231-3e8944ce464d',
        'expires_in' => 631138518,
        'scope' => '/read-limited /activities/update /person/update',
        'name' => 'Sofia Garcia',
        'orcid' => '0000-0002-1825-0097',
    ]);
}

it('builds the authorization URL', function (): void {
    $oauth = new OrcidOAuth('APP-1', 'secret', Environment::Sandbox);

    $url = $oauth->authorizationUrl(Scope::completeAccess(), 'https://example.org/callback?key=abc', state: 'xyz', lang: 'fr');

    expect($url)->toBe(
        'https://sandbox.orcid.org/oauth/authorize?client_id=APP-1&response_type=code'
        .'&scope=%2Fread-limited%20%2Factivities%2Fupdate%20%2Fperson%2Fupdate'
        .'&redirect_uri=https%3A%2F%2Fexample.org%2Fcallback%3Fkey%3Dabc&state=xyz&lang=fr'
    );
});

it('leaves out optional authorization URL parameters', function (): void {
    $url = new OrcidOAuth('APP-1', 'secret')->authorizationUrl([Scope::Authenticate], 'https://example.org/cb', extra: ['show_login' => 'true']);

    expect($url)->toBe('https://orcid.org/oauth/authorize?client_id=APP-1&response_type=code&scope=%2Fauthenticate&redirect_uri=https%3A%2F%2Fexample.org%2Fcb&show_login=true');
});

it('exchanges an authorization code for a token', function (): void {
    $mockClient = new MockClient([ExchangeAuthorizationCode::class => threeLeggedTokenResponse()]);

    $token = new OrcidOAuth('APP-1', 'secret', Environment::Sandbox)
        ->withMockClient($mockClient)
        ->exchangeCode('Q70Y3A', 'https://example.org/callback');

    expect($token)->toBeInstanceOf(OrcidToken::class)
        ->and($token->accessToken)->toBe('f5af9f51-07e6-4332-8f1a-c0c11c1e3728')
        ->and($token->refreshToken)->toBe('f725f747-3a65-49f6-a231-3e8944ce464d')
        ->and($token->expiresIn)->toBe(631138518)
        ->and($token->scopes)->toBe(Scope::completeAccess())
        ->and($token->orcid)->toBe('0000-0002-1825-0097')
        ->and($token->name)->toBe('Sofia Garcia')
        ->and($token->hasScope(Scope::ActivitiesUpdate))->toBeTrue()
        ->and($token->hasScope(Scope::Webhook))->toBeFalse();

    $mockClient->assertSent(function (ExchangeAuthorizationCode $request, $response): bool {
        /** @var PendingRequest $pending */
        $pending = $response->getPendingRequest();

        return $pending->getUrl() === 'https://sandbox.orcid.org/oauth/token'
            && $pending->body()?->all() === [
                'grant_type' => 'authorization_code',
                'code' => 'Q70Y3A',
                'redirect_uri' => 'https://example.org/callback',
                'client_id' => 'APP-1',
                'client_secret' => 'secret',
            ];
    });
});

it('requests a client credentials token', function (): void {
    $mockClient = new MockClient([RequestClientCredentialsToken::class => MockResponse::make([
        'access_token' => '4bed1e13-7792-4129-9f07-aaf7b88ba88f',
        'token_type' => 'bearer',
        'refresh_token' => '2d76d8d0-6fd6-426b-a017-61e0ceda0ad2',
        'expires_in' => 631138518,
        'scope' => '/read-public',
        'orcid' => null,
    ])]);

    $token = new OrcidOAuth('APP-1', 'secret')->withMockClient($mockClient)->clientCredentials();

    expect($token->scopes)->toBe([Scope::ReadPublic])
        ->and($token->orcid)->toBeNull()
        ->and($token->name)->toBeNull();

    $mockClient->assertSent(function (RequestClientCredentialsToken $request, $response): bool {
        /** @var PendingRequest $pending */
        $pending = $response->getPendingRequest();

        return $pending->body()?->all() === [
            'grant_type' => 'client_credentials',
            'scope' => '/read-public',
            'client_id' => 'APP-1',
            'client_secret' => 'secret',
        ];
    });
});

it('throws when the token endpoint rejects the request', function (): void {
    $mockClient = new MockClient([ExchangeAuthorizationCode::class => MockResponse::make([
        'error' => 'invalid_grant',
        'error_description' => 'Invalid authorization code: Q70Y3A',
    ], 400)]);

    try {
        new OrcidOAuth('APP-1', 'secret')->withMockClient($mockClient)->exchangeCode('Q70Y3A', 'https://example.org/callback');
        $this->fail('Expected an exception');
    } catch (OrcidValidationException $exception) {
        expect($exception->oauthError)->toBe('invalid_grant')
            ->and($exception->getMessage())->toBe('ORCID request failed with status 400: Invalid authorization code: Q70Y3A')
            ->and($exception->isRetryable())->toBeFalse();
    }
});

it('refuses to send token requests through another connector', function (): void {
    $mockClient = new MockClient([RequestClientCredentialsToken::class => MockResponse::make()]);

    Orcid::public()->withMockClient($mockClient)->send(new RequestClientCredentialsToken);
})->throws(RuntimeException::class, 'must be sent through an OrcidOAuth connector');

it('encodes and decodes scopes, ignoring unknown ones', function (): void {
    expect(Scope::encode([Scope::ReadLimited, Scope::ActivitiesUpdate]))->toBe('/read-limited /activities/update')
        ->and(Scope::decode(' /read-limited  /something-new /activities/update '))->toBe([Scope::ReadLimited, Scope::ActivitiesUpdate])
        ->and(Scope::decode(''))->toBe([]);
});
