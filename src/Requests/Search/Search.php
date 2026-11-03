<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Search;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Search\SearchResults;
use VincentAuger\OrcidSdk\Traits\Requests\HasSearchQuery;

/**
 * Search the registry (GET /search/?q=...): the matching ORCID iDs and the total found. Public and Member API, with
 * any token or none (`c8/`).
 */
final class Search extends Request
{
    use HasSearchQuery;

    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return $this->searchEndpoint('search');
    }

    public function createDtoFromResponse(Response $response): SearchResults
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return SearchResults::fromArray($data);
    }
}
