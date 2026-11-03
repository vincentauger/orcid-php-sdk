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
use VincentAuger\OrcidSdk\Data\Activities\ActivitiesSummary;
use VincentAuger\OrcidSdk\Data\Person\Person;
use VincentAuger\OrcidSdk\Enums\ApiAccess;
use VincentAuger\OrcidSdk\Enums\Environment;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;
use VincentAuger\OrcidSdk\Resources\AffiliationsResource;
use VincentAuger\OrcidSdk\Resources\FundingsResource;
use VincentAuger\OrcidSdk\Resources\PeerReviewsResource;
use VincentAuger\OrcidSdk\Resources\RecordResource;
use VincentAuger\OrcidSdk\Resources\ResearchResourcesResource;
use VincentAuger\OrcidSdk\Resources\WorksResource;
use VincentAuger\OrcidSdk\Support\OrcidId;

/**
 * ORCID record API connector (Public or Member API).
 *
 * The connector is stateless: it only holds the bearer token it is given and,
 * for a member connector, the record that token was granted on (ADR 0003).
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

    /**
     * The most items ORCID takes in one bulk call (works: getMany(), createMany()). Above it, the SDK throws
     * instead of splitting the call into several requests (ADR 0002)
     */
    public const int BULK_LIMIT = 100;

    protected int $connectTimeout = 10;

    protected int $requestTimeout = 30;

    /**
     * The normalised ORCID iD of the record this connector is bound to, if any
     */
    public readonly ?string $orcid;

    /**
     * @param  Scope[]|null  $scopes  The token's scopes, if known (e.g. stored with the token). When given,
     *                                requests needing a scope the token lacks throw MissingScopeException
     *                                before anything is sent.
     * @param  string|null  $orcid  The record the token was granted on (Member API only), bare iD or ORCID URL
     */
    public function __construct(
        public readonly ApiAccess $access = ApiAccess::Public,
        private readonly ?string $accessToken = null,
        public readonly Environment $environment = Environment::Production,
        public readonly ?array $scopes = null,
        ?string $orcid = null,
    ) {
        if ($this->access === ApiAccess::Member && $this->accessToken === null) {
            throw new \InvalidArgumentException('An access token is required for member API access');
        }

        if ($this->access === ApiAccess::Public && $orcid !== null) {
            throw new \InvalidArgumentException('Only a member API connector can be bound to a record: use record($id) to read one');
        }

        $this->orcid = $orcid === null ? null : OrcidId::normalize($orcid);
    }

    /**
     * Member API client, with the values an integration stores for a token.
     *
     * With `$orcid` (the record a 3-legged token was granted on), the record's sections hang off the connector:
     * `Orcid::member(accessToken: $token, orcid: $id)->affiliations()`. Without it (e.g. a /read-public client
     * credentials token, for the Member API's higher limits), the connector only reads, through record($id).
     *
     * @param  Scope[]|null  $scopes  The token's scopes, if known; see the constructor
     *
     * @throws \InvalidArgumentException when $orcid is not a valid ORCID iD
     */
    public static function member(
        string $accessToken,
        ?string $orcid = null,
        ?array $scopes = null,
        Environment $environment = Environment::Production,
    ): self {
        return new self(ApiAccess::Member, $accessToken, $environment, $scopes, $orcid);
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
     * Reads on any record. Accepts a bare iD or an ORCID URL.
     *
     * @throws \InvalidArgumentException when $orcid is not a valid ORCID iD
     */
    public function record(string $orcid): RecordResource
    {
        return new RecordResource($this, OrcidId::normalize($orcid));
    }

    /**
     * The affiliations of the record this connector is bound to
     *
     * @throws \LogicException when the connector is not bound to a record
     */
    public function affiliations(): AffiliationsResource
    {
        return new AffiliationsResource($this, $this->boundOrcid(__FUNCTION__));
    }

    /**
     * The works of the record this connector is bound to
     *
     * @throws \LogicException when the connector is not bound to a record
     */
    public function works(): WorksResource
    {
        return new WorksResource($this, $this->boundOrcid(__FUNCTION__));
    }

    /**
     * The fundings of the record this connector is bound to
     *
     * @throws \LogicException when the connector is not bound to a record
     */
    public function fundings(): FundingsResource
    {
        return new FundingsResource($this, $this->boundOrcid(__FUNCTION__));
    }

    /**
     * The peer reviews of the record this connector is bound to
     *
     * @throws \LogicException when the connector is not bound to a record
     */
    public function peerReviews(): PeerReviewsResource
    {
        return new PeerReviewsResource($this, $this->boundOrcid(__FUNCTION__));
    }

    /**
     * The research resources of the record this connector is bound to
     *
     * @throws \LogicException when the connector is not bound to a record
     */
    public function researchResources(): ResearchResourcesResource
    {
        return new ResearchResourcesResource($this, $this->boundOrcid(__FUNCTION__));
    }

    /**
     * The person section of the record this connector is bound to
     *
     * @throws \LogicException when the connector is not bound to a record
     */
    public function person(): Person
    {
        return $this->record($this->boundOrcid(__FUNCTION__))->person();
    }

    /**
     * Summary of every activity on the record this connector is bound to
     *
     * @throws \LogicException when the connector is not bound to a record
     */
    public function activities(): ActivitiesSummary
    {
        return $this->record($this->boundOrcid(__FUNCTION__))->activities();
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

    private function boundOrcid(string $method): string
    {
        return $this->orcid ?? throw new \LogicException(sprintf(
            'Orcid::%s() needs a connector bound to a record: Orcid::member(accessToken: ..., orcid: ...). Use record($id) to read any record.',
            $method,
        ));
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
