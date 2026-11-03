<?php

declare(strict_types=1);

use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Data\Record\RecordSummaryAffiliation;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Enums\Visibility;

/*
 * Talks to the ORCID sandbox without fixtures (composer test:live): checks that ORCID still behaves as recorded.
 * Skipped without a .env file. Reads the hand-made content of the sandbox record (issue #3).
 */

it('reads its own record through the Member API on the sandbox', function (): void {
    $this->requiresSandbox();

    $orcid = $this->getMemberApiClient();
    // a marked role title: the record summary's affiliations have no department name
    $putCode = $orcid->affiliations()->create(AffiliationType::Service, testAffiliation('live record', roleTitle: OrcidFixture::TEST_MARKER.' live record'));

    try {
        $person = $orcid->person();
        $biography = $orcid->biography();
        $emails = $orcid->emails();
        $details = $orcid->personalDetails();
        $activities = $orcid->activities();
        $whole = $orcid->wholeRecord();
        $summary = $orcid->recordSummary();
    } finally {
        $orcid->affiliations()->delete(AffiliationType::Service, $putCode);
    }

    $putCodes = static fn (array $items): array => array_map(fn (Affiliation|RecordSummaryAffiliation $item): int => $item->putCode, $items);

    expect($person->name?->familyName)->toBe('Auger')
        ->and($biography?->content)->toBe('A test bio.')
        // /read-limited: the researcher's limited email too
        ->and($emails[0]->visibility)->toBe(Visibility::Limited)
        ->and($person->emails)->toEqual($emails)
        ->and($details->name?->displayName())->toBe('V JP Auger')
        ->and($putCodes($activities->affiliations(AffiliationType::Service)))->toContain($putCode)
        ->and($whole->orcid)->toBe($this->orcidId())
        ->and($whole->person->name?->familyName)->toBe('Auger')
        ->and($putCodes($whole->activities->affiliations(AffiliationType::Service)))->toContain($putCode)
        ->and($summary->orcid)->toBe($this->orcidId())
        ->and($putCodes($summary->professionalActivities))->toContain($putCode)
        ->and($summary->works->selfAsserted)->toBeGreaterThanOrEqual(1);
})->group('live');

it('reads any record through the Public API without a token on the sandbox', function (): void {
    $this->requiresSandbox();

    $record = $this->getPublicApiClient()->record($this->orcidId());

    $person = $record->person();
    $whole = $record->wholeRecord();

    // public items only: the limited email is not returned
    expect($person->name?->familyName)->toBe('Auger')
        ->and($person->emails)->toBeEmpty()
        ->and($record->emails())->toBeEmpty()
        ->and($record->biography()?->content)->toBe('A test bio.')
        ->and($record->personalDetails()->name?->displayName())->toBe('V JP Auger')
        ->and($record->activities()->workCount())->toBeGreaterThanOrEqual(1)
        ->and($whole->person->emails)->toBeEmpty()
        ->and($whole->activities->workCount())->toBeGreaterThanOrEqual(1);
})->group('live');
