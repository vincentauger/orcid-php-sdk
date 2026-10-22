<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk;

use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Traits\Plugins\HasTimeout;
use VincentAuger\OrcidSdk\Concerns\ThrowsOrcidExceptions;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Enums\ApiAccess;
use VincentAuger\OrcidSdk\Enums\Environment;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;

/**
 * ORCID record API connector (Public or Member API).
 *
 * The connector is stateless: it only holds the bearer token it is given.
 * Obtaining, storing and refreshing tokens is the caller's responsibility
 * (see OrcidOAuth for the token endpoints).
 *
 * Retries are disabled on purpose: callers (e.g. a queue) decide whether
 * and when a failed request should be retried.
 *
 * The base URL is the host only: record requests add the API version
 * themselves (/v3.0/...), while webhook endpoints are not versioned.
 */
final class Orcid extends Connector
{
    use HasTimeout;
    use ThrowsOrcidExceptions;

    protected int $connectTimeout = 10;

    protected int $requestTimeout = 30;

    /**
     * @param  Scope[]|null  $scopes  The token's scopes, if known (e.g. stored with the token). When given,
     *                                requests needing a scope the token lacks throw MissingScopeException
     *                                before anything is sent.
     */
    public function __construct(
        public readonly ApiAccess $access = ApiAccess::Public,
        private readonly ?string $accessToken = null,
        public readonly Environment $environment = Environment::Production,
        public readonly ?array $scopes = null,
    ) {
        if ($this->access === ApiAccess::Member && $this->accessToken === null) {
            throw new \InvalidArgumentException('An access token is required for member API access');
        }
    }

    /**
     * Member API client using a token obtained through the 3-legged OAuth flow
     * (or a client credentials token for member-only 2-legged scopes).
     *
     * @param  Scope[]|null  $scopes  The token's scopes, if known; see the constructor
     */
    public static function member(string $accessToken, Environment $environment = Environment::Production, ?array $scopes = null): self
    {
        return new self(ApiAccess::Member, $accessToken, $environment, $scopes);
    }

    /**
     * Public API client. ORCID strongly recommends using a /read-public
     * client credentials token; anonymous access is more heavily rate limited.
     */
    public static function public(?string $accessToken = null, Environment $environment = Environment::Production): self
    {
        return new self(ApiAccess::Public, $accessToken, $environment);
    }

    /**
     * Refuse requests this client cannot make, before anything is sent
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        $request = $pendingRequest->getRequest();

        if ($request instanceof RequiresMemberApi && $this->access !== ApiAccess::Member) {
            throw new \LogicException(sprintf('%s requires the Member API: use Orcid::member()', $request::class));
        }

        if ($request instanceof RequiresScopes && $this->scopes !== null) {
            $missing = array_udiff($request->requiredScopes(), $this->scopes, static fn (Scope $a, Scope $b): int => strcmp($a->value, $b->value));

            if ($missing !== []) {
                throw new MissingScopeException(sprintf(
                    '%s requires the %s scope(s), which this token does not have',
                    $request::class,
                    Scope::encode($missing),
                ));
            }
        }
    }

    public function resolveBaseUrl(): string
    {
        return $this->environment->apiUrl($this->access);
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.orcid+json',
            'Content-Type' => 'application/vnd.orcid+json',
            'User-Agent' => 'VincentAuger/ORCID-PHP-SDK PHP/'.PHP_VERSION,
        ];
    }

    protected function defaultAuth(): ?Authenticator
    {
        return $this->accessToken === null ? null : new TokenAuthenticator($this->accessToken);
    }

    /**
     * Override the default timeouts (in seconds)
     */
    public function withTimeouts(int $connectTimeout, int $requestTimeout): self
    {
        $this->connectTimeout = $connectTimeout;
        $this->requestTimeout = $requestTimeout;

        return $this;
    }
}
