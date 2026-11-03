<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Tests\BaseTest;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Search\ExpandedSearchResult;
use VincentAuger\OrcidSdk\Data\Search\ExpandedSearchResults;
use VincentAuger\OrcidSdk\Data\Search\SearchResults;
use VincentAuger\OrcidSdk\Enums\CsvSearchField;
use VincentAuger\OrcidSdk\Enums\Environment;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Search\CsvSearch;
use VincentAuger\OrcidSdk\Requests\Search\Search;

/*
 * Searches only ever query the test record (by its iD): other sandbox users' names must never end up in fixtures.
 * Each test is a chain (token, searches, revoke): to re-record, delete its whole folder.
 */

/**
 * Run the searches with a member connector holding a fresh /read-public client credentials token, then revoke it
 *
 * @template T
 *
 * @param  callable(Orcid): T  $search
 * @return T
 */
function withReadPublicConnector(BaseTest $test, MockClient $mockClient, callable $search): mixed
{
    $oauth = $test->getOAuthClient()->withMockClient($mockClient);
    $token = $oauth->clientCredentials([Scope::ReadPublic]);

    try {
        return $search(Orcid::member(accessToken: $token->accessToken, environment: Environment::Sandbox)->withMockClient($mockClient));
    } finally {
        $oauth->revoke($token->accessToken);
    }
}

it('searches with a /read-public token on the Member API: the matching iDs and the total', function (): void {
    $folder = 'search/member';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/client-credentials"),
        new OrcidFixture("{$folder}/search"),
        new OrcidFixture("{$folder}/revoke"),
    ]);

    $results = withReadPublicConnector($this, $mockClient, fn (Orcid $orcid): SearchResults => $orcid->search('orcid:'.$this->orcidId()));

    expect($results)->toBeInstanceOf(SearchResults::class)
        ->and($results->orcids)->toHaveCount(1)
        ->and($results->orcids[0])->toBeIn($this->recordedIds())
        ->and($results->numFound)->toBe(1);
});

it('runs an expanded search with a /read-public token on the Member API: names and institutions too', function (): void {
    $folder = 'search/expanded-member';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/client-credentials"),
        new OrcidFixture("{$folder}/expanded-search"),
        new OrcidFixture("{$folder}/revoke"),
    ]);

    $results = withReadPublicConnector($this, $mockClient, fn (Orcid $orcid): ExpandedSearchResults => $orcid->expandedSearch('orcid:'.$this->orcidId()));
    $result = $results->results[0];

    // the hand-made content (issue #3); the email is limited, so search doesn't show it
    expect($results)->toBeInstanceOf(ExpandedSearchResults::class)
        ->and($results->numFound)->toBe(1)
        ->and($results->results)->toHaveCount(1)
        ->and($result)->toBeInstanceOf(ExpandedSearchResult::class)
        ->and($result->orcid)->toBeIn($this->recordedIds())
        ->and($result->givenNames)->toBe('Vincent')
        ->and($result->familyName)->toBe('Auger')
        ->and($result->creditName)->toBe('V JP Auger')
        ->and($result->displayName())->toBe('V JP Auger')
        ->and($result->otherNames)->toBe(['JP Auger'])
        ->and($result->emails)->toBe([])
        ->and($result->institutionNames)->toContain('Fisheries and Oceans Canada');
});

it('searches through the Public API without a token, the same way', function (): void {
    $mockClient = new MockClient([
        new OrcidFixture('search/public/search'),
        new OrcidFixture('search/public/expanded-search'),
    ]);
    $orcid = $this->getPublicApiClient()->withMockClient($mockClient);

    $results = $orcid->search('orcid:'.$this->orcidId());
    $expanded = $orcid->expandedSearch('orcid:'.$this->orcidId());

    expect($results->orcids)->toHaveCount(1)
        ->and($results->orcids[0])->toBeIn($this->recordedIds())
        ->and($results->numFound)->toBe(1)
        ->and($expanded->results[0]->orcid)->toBeIn($this->recordedIds())
        ->and($expanded->results[0]->familyName)->toBe('Auger');
});

it('runs a CSV search with a /read-public token on the Member API: the columns asked for, as ORCID writes them', function (): void {
    $folder = 'search/csv-member';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/client-credentials"),
        new OrcidFixture("{$folder}/csv-search"),
        new OrcidFixture("{$folder}/revoke"),
    ]);

    $csv = withReadPublicConnector($this, $mockClient, fn (Orcid $orcid): string => $orcid->csvSearch(
        'orcid:'.$this->orcidId(),
        [CsvSearchField::Orcid, CsvSearchField::GivenNames, CsvSearchField::FamilyName, CsvSearchField::CurrentInstitutionAffiliationName],
    ));
    $rows = array_map(fn (string $line): array => str_getcsv($line, escape: ''), explode("\n", trim($csv)));

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['orcid', 'given-names', 'family-name', 'current-institution-affiliation-name'])
        ->and($rows[1][0])->toBeIn($this->recordedIds())
        ->and(array_slice($rows[1], 1))->toBe(['Vincent', 'Auger', 'Fisheries and Oceans Canada']);
});

it('runs a CSV search with every column by default', function (): void {
    // ORCID's default columns, its header in c8/x02: the SDK sends them, so the columns don't depend on ORCID's default
    expect(new CsvSearch('orcid:0000-0002-1825-0097')->query()->get('fl'))
        ->toBe('orcid,email,given-names,family-name,given-and-family-names,current-institution-affiliation-name,past-institution-affiliation-name,credit-name,other-names')
        ->and(new CsvSearch('orcid:0000-0002-1825-0097')->headers()->get('Accept'))->toBe('text/csv');
});

it('refuses a CSV search without columns, before sending', function (): void {
    $this->getPublicApiClient()->withMockClient(new MockClient([]))->csvSearch('orcid:'.$this->orcidId(), []);
})->throws(InvalidArgumentException::class, 'at least one column');

it('refuses a page ORCID would reject, before sending', function (int $start, int $rows, string $message): void {
    // nothing is sent: rows above 1000 or below 0 are 400, error 9012 (c8/q05, q10), a negative start a 500 (q08)
    expect(fn (): SearchResults => $this->getPublicApiClient()->withMockClient(new MockClient([]))->search('orcid:'.$this->orcidId(), $start, $rows))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'too many rows' => [0, 1001, 'between 0 and 1000 rows'],
    'negative rows' => [0, -1, 'between 0 and 1000 rows'],
    'negative start' => [-1, 10, 'start at 0 or after'],
]);

it('refuses a blank query, before sending', function (): void {
    // ORCID answers an empty page (c8/q12), which would read as "no match"
    $this->getPublicApiClient()->withMockClient(new MockClient([]))->search('  ');
})->throws(InvalidArgumentException::class, 'needs a query');

it('accepts the edges of ORCID\'s paging', function (): void {
    // ORCID accepts 0 to 1000 rows (c8/q09, q04) and any start, even past the last result (q19, q07)
    expect(new Search('orcid:0000-0002-1825-0097', 100000, 1000)->query()->all())->toBe(['q' => 'orcid:0000-0002-1825-0097', 'start' => 100000, 'rows' => 1000])
        ->and(new Search('orcid:0000-0002-1825-0097', 0, 0)->query()->get('rows'))->toBe(0);
});
