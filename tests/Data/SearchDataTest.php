<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Search\ExpandedSearchResult;
use VincentAuger\OrcidSdk\Data\Search\ExpandedSearchResults;
use VincentAuger\OrcidSdk\Data\Search\SearchResults;

it('reads search results without a match: ORCID sends a null result list', function (): void {
    // c8/q14
    $results = SearchResults::fromArray(['result' => null, 'num-found' => 0]);

    expect($results->orcids)->toBe([])
        ->and($results->numFound)->toBe(0);
});

it('reads the ORCID iDs of search results, skipping a result without one', function (): void {
    $results = SearchResults::fromArray([
        'result' => [
            ['orcid-identifier' => ['uri' => 'https://orcid.org/0000-0002-1825-0097', 'path' => '0000-0002-1825-0097', 'host' => 'orcid.org']],
            ['orcid-identifier' => null],
        ],
        'num-found' => 2,
    ]);

    expect($results->orcids)->toBe(['0000-0002-1825-0097'])
        ->and($results->numFound)->toBe(2);
});

it('reads expanded search results without a match: ORCID sends a null result list', function (): void {
    // c8/e04
    $results = ExpandedSearchResults::fromArray(['expanded-result' => null, 'num-found' => 1]);

    expect($results->results)->toBe([])
        ->and($results->numFound)->toBe(1);
});

it('reads an expanded search result with only an iD: the names are private', function (): void {
    $results = ExpandedSearchResults::fromArray([
        'expanded-result' => [
            ['orcid-id' => '0000-0002-1825-0097', 'given-names' => null, 'other-name' => null, 'email' => [], 'institution-name' => ['', 'Brown University']],
            ['given-names' => 'No iD'],
        ],
        'num-found' => 2,
    ]);

    expect($results->results)->toHaveCount(1)
        ->and($results->results[0])->toEqual(new ExpandedSearchResult('0000-0002-1825-0097', institutionNames: ['Brown University']))
        ->and($results->results[0]->displayName())->toBeNull();
});

it('names an expanded search result by its credit name, else its given and family names', function (): void {
    expect(new ExpandedSearchResult('0000-0002-1825-0097', 'Josiah', 'Carberry', 'J. S. Carberry')->displayName())->toBe('J. S. Carberry')
        ->and(new ExpandedSearchResult('0000-0002-1825-0097', 'Josiah', 'Carberry')->displayName())->toBe('Josiah Carberry')
        ->and(new ExpandedSearchResult('0000-0002-1825-0097', familyName: 'Carberry')->displayName())->toBe('Carberry');
});
