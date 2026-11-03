<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Search;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Enums\CsvSearchField;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Traits\Requests\HasSearchQuery;

/**
 * Search the registry (GET /csv-search/?q=...&fl=...): one CSV row per matching record, after a header row of the
 * columns asked for. Public and Member API, with any token or none (`c8/`). There is no total found.
 */
final class CsvSearch extends Request
{
    use HasSearchQuery {
        HasSearchQuery::__construct as private searchQuery;
        HasSearchQuery::defaultQuery as private searchQueryParameters;
    }

    protected Method $method = Method::GET;

    /**
     * @var non-empty-list<CsvSearchField>
     */
    private readonly array $fields;

    /**
     * @param  list<CsvSearchField>|null  $fields  The columns, in order; null for all of them
     *
     * @throws \InvalidArgumentException on an empty list of columns, a blank query or a page ORCID would reject
     */
    public function __construct(string $solrQuery, ?array $fields = null, int $start = 0, int $rows = Orcid::SEARCH_ROWS_LIMIT)
    {
        $this->searchQuery($solrQuery, $start, $rows);

        $fields ??= CsvSearchField::cases();

        if ($fields === []) {
            throw new \InvalidArgumentException('An ORCID CSV search needs at least one column');
        }

        $this->fields = $fields;
    }

    public function resolveEndpoint(): string
    {
        return $this->searchEndpoint('csv-search');
    }

    /**
     * @return array{q: string, start: int, rows: int, fl: string}
     */
    protected function defaultQuery(): array
    {
        return [
            ...$this->searchQueryParameters(),
            'fl' => implode(',', array_map(static fn (CsvSearchField $field): string => $field->value, $this->fields)),
        ];
    }

    /**
     * ORCID only answers CSV to a CSV Accept header: anything else is 406 (`c8/x06`)
     *
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'text/csv'];
    }

    public function createDtoFromResponse(Response $response): string
    {
        return $response->body();
    }
}
