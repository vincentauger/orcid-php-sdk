<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Traits\Requests;

use VincentAuger\OrcidSdk\Enums\ApiVersion;
use VincentAuger\OrcidSdk\Orcid;

/**
 * Search requests take a raw Solr query and a page (start, rows), under /v3.0/<endpoint>/
 */
trait HasSearchQuery
{
    /**
     * @param  string  $solrQuery  A Solr query, e.g. `family-name:Carberry AND affiliation-org-name:"Brown University"`
     * @param  int  $start  The first result to return (0-based)
     * @param  int  $rows  How many results to return
     *
     * @throws \InvalidArgumentException on a blank query, a negative start, or rows outside 0 to Orcid::SEARCH_ROWS_LIMIT
     */
    public function __construct(
        private readonly string $solrQuery,
        private readonly int $start = 0,
        private readonly int $rows = Orcid::SEARCH_ROWS_LIMIT,
    ) {
        // ORCID answers a blank query with an empty page (c8/q12), which would read as "no match"
        if (trim($solrQuery) === '') {
            throw new \InvalidArgumentException('An ORCID search needs a query');
        }

        // a negative start is a 500 from ORCID's Solr (c8/q08); there is no upper bound (q19)
        if ($start < 0) {
            throw new \InvalidArgumentException(sprintf('An ORCID search page must start at 0 or after, %d given', $start));
        }

        // beyond: 400, error 9012 (c8/q05, q10)
        if ($rows < 0 || $rows > Orcid::SEARCH_ROWS_LIMIT) {
            throw new \InvalidArgumentException(sprintf(
                'ORCID returns between 0 and %d rows per search, %d given: page through the results with start',
                Orcid::SEARCH_ROWS_LIMIT,
                $rows,
            ));
        }
    }

    protected function searchEndpoint(string $endpoint): string
    {
        return '/'.ApiVersion::V3_0->value.'/'.$endpoint.'/';
    }

    /**
     * @return array{q: string, start: int, rows: int}
     */
    protected function defaultQuery(): array
    {
        return ['q' => $this->solrQuery, 'start' => $this->start, 'rows' => $this->rows];
    }
}
