<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Fundings;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Fundings\FundingGroups;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * List the fundings of a record, grouped (GET /{orcid}/fundings)
 */
final class ListFundings extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'fundings');
    }

    public function createDtoFromResponse(Response $response): FundingGroups
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return FundingGroups::fromArray($data);
    }
}
