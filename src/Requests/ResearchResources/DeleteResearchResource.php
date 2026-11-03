<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\ResearchResources;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Delete a research resource (DELETE /{orcid}/research-resource/{put-code}). ORCID answers 204.
 * Only the client that created the item can delete it.
 */
final class DeleteResearchResource extends Request implements RequiresMemberApi, RequiresScopes
{
    use HasRecordEndpoint;

    protected Method $method = Method::DELETE;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'research-resource/'.$this->putCode);
    }

    public function requiredScopes(): array
    {
        return [Scope::ActivitiesUpdate];
    }
}
