<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Enums\CsvSearchField;
use VincentAuger\OrcidSdk\Enums\Environment;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Orcid;

/*
 * Talks to the ORCID sandbox without fixtures (composer test:live): checks that ORCID still behaves as recorded.
 * Skipped without a .env file. Only queries the test record.
 */

it('searches the sandbox for the test record with a /read-public token on the Member API', function (): void {
    $this->requiresSandbox();

    $oauth = $this->getOAuthClient();
    $token = $oauth->clientCredentials([Scope::ReadPublic]);
    $query = 'orcid:'.$this->orcidId();

    try {
        $orcid = Orcid::member(accessToken: $token->accessToken, environment: Environment::Sandbox);
        $results = $orcid->search($query);
        $expanded = $orcid->expandedSearch($query);
        $csv = $orcid->csvSearch($query, [CsvSearchField::Orcid, CsvSearchField::FamilyName]);
        // past the last result: an empty page, still with the total (c8/q07)
        $beyond = $orcid->search($query, start: 10);
        // ORCID's largest page
        $largest = $orcid->search($query, rows: Orcid::SEARCH_ROWS_LIMIT);
    } finally {
        $oauth->revoke($token->accessToken);
    }

    expect($results->orcids)->toBe([$this->orcidId()])
        ->and($results->numFound)->toBe(1)
        ->and($expanded->results[0]->orcid)->toBe($this->orcidId())
        ->and($expanded->results[0]->familyName)->toBe('Auger')
        ->and($csv)->toBe("orcid,family-name\n{$this->orcidId()},Auger\n")
        ->and($beyond->orcids)->toBe([])
        ->and($beyond->numFound)->toBe(1)
        ->and($largest->orcids)->toBe([$this->orcidId()]);
})->group('live');
