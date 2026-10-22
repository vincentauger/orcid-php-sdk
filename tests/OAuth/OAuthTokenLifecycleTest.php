<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Exceptions\OrcidForbiddenException;
use VincentAuger\OrcidSdk\Requests\OAuth\GetUserInfo;
use VincentAuger\OrcidSdk\Requests\OAuth\RefreshToken;
use VincentAuger\OrcidSdk\Requests\OAuth\RequestClientCredentialsToken;
use VincentAuger\OrcidSdk\Requests\OAuth\RevokeToken;

/*
 * Recorded against the ORCID sandbox. The token chain uses a throwaway /read-public
 * token, so the 3-legged token in .env is never revoked.
 */

it('gets and revokes a client credentials token', function (): void {
    $mockClient = new MockClient([
        RequestClientCredentialsToken::class => new OrcidFixture('oauth/client-credentials'),
        RevokeToken::class => new OrcidFixture('oauth/revoke'),
    ]);

    $oauth = $this->getOAuthClient()->withMockClient($mockClient);

    $token = $oauth->clientCredentials([Scope::ReadPublic]);

    // ORCID does not issue refresh tokens for client credentials tokens
    expect($token->scopes)->toBe([Scope::ReadPublic])
        ->and($token->orcid)->toBeNull()
        ->and($token->refreshToken)->toBeNull()
        ->and($token->expiresIn)->toBeGreaterThan(0);

    $oauth->revoke($token->accessToken);

    $mockClient->assertSent(fn (RevokeToken $request): bool => $request->body()->get('token') === $token->accessToken);
});

it('refreshes a token, optionally narrowing scopes and revoking the old one', function (): void {
    // Not recorded yet: needs ORCID_REFRESH_TOKEN (bin/sandbox-token.sh saves it).
    // Response as documented by ORCID.
    $mockClient = new MockClient([RefreshToken::class => MockResponse::make([
        'access_token' => '589a0ac6-5e1f-4b55-9bba-2ab8f1a7d10f',
        'token_type' => 'bearer',
        'refresh_token' => 'a1b7a1f4-8f1f-4f6e-8b5e-1f3f4d6f9e2a',
        'expires_in' => 631138518,
        'scope' => '/read-limited',
        'name' => 'Sofia Garcia',
        'orcid' => '0000-0002-1825-0097',
    ])]);

    $token = $this->getOAuthClient()->withMockClient($mockClient)
        ->refresh('old-refresh-token', scopes: [Scope::ReadLimited], revokeOld: true, expiresIn: 3600);

    expect($token->scopes)->toBe([Scope::ReadLimited]);

    $mockClient->assertSent(fn (RefreshToken $request): bool => $request->body()->all() === [
        'grant_type' => 'refresh_token',
        'refresh_token' => 'old-refresh-token',
        'revoke_old' => 'true',
        'scope' => '/read-limited',
        'expires_in' => '3600',
    ]);
});

it('gets the user info of an OpenID token', function (): void {
    // Not recorded: needs a token with the openid scope (OpenID / implicit flow).
    $mockClient = new MockClient([GetUserInfo::class => MockResponse::make([
        'id' => 'https://orcid.org/0000-0002-1825-0097',
        'sub' => '0000-0002-1825-0097',
        'name' => null,
        'family_name' => 'Carberry',
        'given_name' => 'Josiah',
    ])]);

    $userInfo = $this->getOAuthClient()->withMockClient($mockClient)->userInfo('openid-token');

    expect($userInfo->orcid)->toBe('0000-0002-1825-0097')
        ->and($userInfo->orcidUrl)->toBe('https://orcid.org/0000-0002-1825-0097')
        ->and($userInfo->givenName)->toBe('Josiah')
        ->and($userInfo->familyName)->toBe('Carberry')
        ->and($userInfo->name)->toBeNull();

    $mockClient->assertSent(fn (GetUserInfo $request, $response): bool => $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer openid-token');
});

it('refuses user info for a token without the openid scope', function (): void {
    $mockClient = new MockClient([GetUserInfo::class => new OrcidFixture('oauth/userinfo-without-openid')]);

    expect(fn () => $this->getOAuthClient()->withMockClient($mockClient)->userInfo($_ENV['ORCID_ACCESS_TOKEN'] ?? 'test-token'))
        ->toThrow(function (OrcidForbiddenException $exception): void {
            expect($exception->oauthError)->toBe('access_denied')
                ->and($exception->oauthErrorDescription)->toBe('access_token is invalid')
                ->and($exception->isMissingScope())->toBeFalse();
        });
});
