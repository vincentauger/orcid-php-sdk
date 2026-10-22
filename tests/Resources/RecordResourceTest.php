<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Data\Affiliations\AffiliationInput;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Requests\Person\GetPerson;

/*
 * Recorded against the ORCID sandbox. Personal values are redacted in the
 * fixtures, so assertions check structure rather than names.
 */

it('reads the person section through the Member API', function (): void {
    $mockClient = new MockClient([GetPerson::class => new OrcidFixture('record/person-member')]);

    $person = $this->getMemberApiClient()->withMockClient($mockClient)->record($this->orcidId())->person();

    expect($person->name)->not->toBeNull()
        ->and($person->name?->displayName())->toBeString()
        ->and($person->lastModifiedAt)->toBeInstanceOf(DateTimeImmutable::class);

    // /read-limited gives access to the emails the person shared with trusted parties
    foreach ($person->emails as $email) {
        expect($email->email)->not->toBe('');
    }
});

it('reads the person section through the Public API without a token', function (): void {
    $mockClient = new MockClient([GetPerson::class => new OrcidFixture('record/person-public')]);

    $person = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->person();

    expect($person->name)->not->toBeNull();
});

it('summarises the activities of a record', function (): void {
    $mockClient = new MockClient([
        new OrcidFixture('record/activities/create'),
        new OrcidFixture('record/activities/summary'),
        new OrcidFixture('record/activities/delete'),
    ]);

    $record = $this->getMemberApiClient()->withMockClient($mockClient)->record($this->orcidId());

    $putCode = $record->affiliations()->create(AffiliationType::Service, new AffiliationInput(
        organization: new Organization(
            'Fisheries and Oceans Canada',
            new OrganizationAddress('Ottawa', 'CA'),
            DisambiguatedOrganization::ror('https://ror.org/02qa1x782'),
        ),
        departmentName: OrcidFixture::TEST_MARKER.' activities',
    ));

    $summary = $record->activities();

    $record->affiliations()->delete(AffiliationType::Service, $putCode);

    $services = array_map(fn (Affiliation $affiliation): int => $affiliation->putCode, $summary->affiliations(AffiliationType::Service));

    expect($services)->toContain($putCode)
        ->and($summary->workCount)->toBeGreaterThanOrEqual(0)
        ->and($summary->lastModifiedAt)->toBeInstanceOf(DateTimeImmutable::class);
});
