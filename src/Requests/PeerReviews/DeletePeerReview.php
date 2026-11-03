<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\PeerReviews;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Delete a peer review (DELETE /{orcid}/peer-review/{put-code}). ORCID answers 204.
 * Only the client that created the item can delete it.
 */
final class DeletePeerReview extends Request implements RequiresMemberApi, RequiresScopes
{
    use HasRecordEndpoint;

    protected Method $method = Method::DELETE;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'peer-review/'.$this->putCode);
    }

    public function requiredScopes(): array
    {
        return [Scope::ActivitiesUpdate];
    }
}
