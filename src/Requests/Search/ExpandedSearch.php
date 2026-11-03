<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Search;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Search\ExpandedSearchResults;
use VincentAuger\OrcidSdk\Traits\Requests\HasSearchQuery;

/**
 * Search the registry (GET /expanded-search/?q=...): each matching record with its public names, emails and
 * institutions, and the total found. Public and Member API, with any token or none (`c8/`).
 */
final class ExpandedSearch extends Request
{
    use HasSearchQuery;

    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return $this->searchEndpoint('expanded-search');
    }

    public function createDtoFromResponse(Response $response): ExpandedSearchResults
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return ExpandedSearchResults::fromArray($data);
    }
}
