<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Search;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * One page of a search: the matching ORCID iDs, and how many records match in all
 */
final readonly class SearchResults
{
    /**
     * @param  list<string>  $orcids  The ORCID iDs on this page, in ORCID's order
     */
    public function __construct(
        public array $orcids,
        /** Every record matching the query, across all pages */
        public int $numFound,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // a page without results has "result": null
        $orcids = array_map(
            static fn (array $result): ?string => Json::string(Json::object($result, 'orcid-identifier') ?? [], 'path'),
            Json::list($data, 'result'),
        );

        return new self(
            orcids: array_values(array_filter($orcids, is_string(...))),
            numFound: Json::int($data, 'num-found') ?? 0,
        );
    }
}
