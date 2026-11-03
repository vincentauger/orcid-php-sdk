<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Search;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * One page of an expanded search: each matching record with its names and institutions, and how many match in all
 */
final readonly class ExpandedSearchResults
{
    /**
     * @param  list<ExpandedSearchResult>  $results  The records on this page, in ORCID's order
     */
    public function __construct(
        public array $results,
        /** Every record matching the query, across all pages */
        public int $numFound,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // a page without results has "expanded-result": null
        return new self(
            results: array_values(array_filter(array_map(ExpandedSearchResult::fromArray(...), Json::list($data, 'expanded-result')))),
            numFound: Json::int($data, 'num-found') ?? 0,
        );
    }
}
