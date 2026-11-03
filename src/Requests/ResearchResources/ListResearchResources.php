<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\ResearchResources;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceGroups;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * List the research resources of a record, grouped (GET /{orcid}/research-resources)
 */
final class ListResearchResources extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'research-resources');
    }

    public function createDtoFromResponse(Response $response): ResearchResourceGroups
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return ResearchResourceGroups::fromArray($data);
    }
}
