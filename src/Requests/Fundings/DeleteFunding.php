<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Fundings;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Delete a funding (DELETE /{orcid}/funding/{put-code}). ORCID answers 204.
 * Only the client that created the item can delete it.
 */
final class DeleteFunding extends Request implements RequiresMemberApi, RequiresScopes
{
    use HasRecordEndpoint;

    protected Method $method = Method::DELETE;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'funding/'.$this->putCode);
    }

    public function requiredScopes(): array
    {
        return [Scope::ActivitiesUpdate];
    }
}
