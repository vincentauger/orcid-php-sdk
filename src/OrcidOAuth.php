<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\HasTimeout;
use VincentAuger\OrcidSdk\Concerns\ThrowsOrcidExceptions;
use VincentAuger\OrcidSdk\Data\OrcidToken;
use VincentAuger\OrcidSdk\Data\UserInfo;
use VincentAuger\OrcidSdk\Enums\Environment;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Requests\OAuth\ExchangeAuthorizationCode;
use VincentAuger\OrcidSdk\Requests\OAuth\GetUserInfo;
use VincentAuger\OrcidSdk\Requests\OAuth\RefreshToken;
use VincentAuger\OrcidSdk\Requests\OAuth\RequestClientCredentialsToken;
use VincentAuger\OrcidSdk\Requests\OAuth\RevokeToken;

/**
 * ORCID OAuth connector (orcid.org / sandbox.orcid.org).
 *
 * Wraps the stateless OAuth endpoints only: building the authorization URL,
 * exchanging codes, client credentials, refresh, revoke and userinfo. The
 * redirect handling, state and token storage belong to the application.
 */
final class OrcidOAuth extends Connector
{
    use HasTimeout;
    use ThrowsOrcidExceptions;

    protected int $connectTimeout = 10;

    protected int $requestTimeout = 30;

    public function __construct(
        public readonly string $clientId,
        private readonly string $clientSecret,
        public readonly Environment $environment = Environment::Production,
    ) {}

    public function resolveBaseUrl(): string
    {
        return $this->environment->oauthUrl();
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'User-Agent' => 'VincentAuger/ORCID-PHP-SDK PHP/'.PHP_VERSION,
        ];
    }

    /**
     * URL to send the user to for the 3-legged authorization code flow.
     *
     * This only builds a string: generating and checking `state`, and handling
     * the redirect back to `$redirectUri`, is the application's job.
     *
     * @param  Scope[]  $scopes
     * @param  array<string, string>  $extra  Additional ORCID parameters (e.g. `email`, `given_names`, `show_login`)
     */
    public function authorizationUrl(array $scopes, string $redirectUri, ?string $state = null, ?string $lang = null, array $extra = []): string
    {
        $query = array_filter([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'scope' => Scope::encode($scopes),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'lang' => $lang,
        ], static fn (?string $value): bool => $value !== null) + $extra;

        return $this->resolveBaseUrl().'/oauth/authorize?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange an authorization code for a 3-legged token
     */
    public function exchangeCode(string $code, string $redirectUri): OrcidToken
    {
        $request = new ExchangeAuthorizationCode($code, $redirectUri);

        return $request->createDtoFromResponse($this->sendOrFail($request));
    }

    /**
     * Get a 2-legged token for this client (e.g. /read-public, /webhook)
     *
     * @param  Scope[]  $scopes
     */
    public function clientCredentials(array $scopes = [Scope::ReadPublic]): OrcidToken
    {
        $request = new RequestClientCredentialsToken($scopes);

        return $request->createDtoFromResponse($this->sendOrFail($request));
    }

    /**
     * Get a new token from a refresh token, e.g. to narrow its scopes or rotate it.
     *
     * @param  Scope[]|null  $scopes  Same or fewer scopes than the original token; null keeps them all
     * @param  bool  $revokeOld  Revoke the original access token once the new one is issued
     * @param  int|null  $expiresIn  Lifetime of the new token in seconds; at most the original's remaining lifetime
     */
    public function refresh(string $refreshToken, ?array $scopes = null, bool $revokeOld = false, ?int $expiresIn = null): OrcidToken
    {
        $request = new RefreshToken($refreshToken, $scopes, $revokeOld, $expiresIn);

        return $request->createDtoFromResponse($this->sendOrFail($request));
    }

    /**
     * Revoke an access token (and its refresh token), e.g. when a user disconnects their ORCID record
     */
    public function revoke(string $token): void
    {
        $this->sendOrFail(new RevokeToken($token));
    }

    /**
     * OpenID Connect user info for a token (authenticate or implicit flows):
     * who granted it, without needing a call to the record API
     */
    public function userInfo(string $accessToken): UserInfo
    {
        $request = new GetUserInfo($accessToken);

        return $request->createDtoFromResponse($this->sendOrFail($request));
    }

    /**
     * Client credentials sent in the body of every token endpoint request
     *
     * @internal
     *
     * @return array{client_id: string, client_secret: string}
     */
    public function clientCredentialsPayload(): array
    {
        return [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ];
    }
}
