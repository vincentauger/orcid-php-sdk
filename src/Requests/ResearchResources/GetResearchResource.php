<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\ResearchResources;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResource;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get one research resource in full (GET /{orcid}/research-resource/{put-code})
 */
final class GetResearchResource extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'research-resource/'.$this->putCode);
    }

    public function createDtoFromResponse(Response $response): ResearchResource
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return ResearchResource::fromArray($data);
    }
}
