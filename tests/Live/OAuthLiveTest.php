<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Person\Person;
use VincentAuger\OrcidSdk\Enums\Environment;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Exceptions\OrcidAuthException;
use VincentAuger\OrcidSdk\Exceptions\OrcidForbiddenException;
use VincentAuger\OrcidSdk\Orcid;

/*
 * Talks to the ORCID sandbox without fixtures (composer test:live): checks that ORCID still behaves as recorded.
 * Skipped without a .env file. Uses a throwaway /read-public token, so the 3-legged token in .env is never revoked.
 * exchangeCode() (a fresh browser code), refresh() and a successful userInfo() (an openid token) can't run here.
 */

it('gets, uses and revokes a client credentials token on the sandbox', function (): void {
    $this->requiresSandbox();

    $oauth = $this->getOAuthClient();
    $token = $oauth->clientCredentials([Scope::ReadPublic]);
    $record = Orcid::member(accessToken: $token->accessToken, environment: Environment::Sandbox)->record($this->orcidId());

    try {
        $person = $record->person();
        $denied = null;

        try {
            $oauth->userInfo($token->accessToken);
        } catch (OrcidForbiddenException $exception) {
            $denied = $exception;
        }
    } finally {
        $oauth->revoke($token->accessToken);
    }

    // ORCID does not issue refresh tokens for client credentials tokens
    expect($token->scopes)->toBe([Scope::ReadPublic])
        ->and($token->refreshToken)->toBeNull()
        ->and($token->orcid)->toBeNull()
        ->and($person->name?->familyName)->toBe('Auger')
        // no openid scope: 403 access_denied
        ->and($denied?->oauthError)->toBe('access_denied')
        // a revoked token is a 401
        ->and(fn (): Person => $record->person())->toThrow(OrcidAuthException::class);
})->group('live');
