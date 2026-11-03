<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\ResearcherUrls;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\ResearcherUrl;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get one researcher URL (GET /{orcid}/researcher-urls/{put-code})
 */
final class GetResearcherUrl extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'researcher-urls/'.$this->putCode);
    }

    public function createDtoFromResponse(Response $response): ResearcherUrl
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return ResearcherUrl::fromArray($data) ?? throw new \UnexpectedValueException('ORCID returned a researcher URL without a URL');
    }
}
