<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\ResearcherUrls;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\ResearcherUrl;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * List the researcher URLs of a record (GET /{orcid}/researcher-urls)
 */
final class ListResearcherUrls extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'researcher-urls');
    }

    /**
     * @return list<ResearcherUrl>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return ResearcherUrl::listFromArray($data);
    }
}
