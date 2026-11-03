<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Activities\ActivitiesSummary;
use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Data\Fundings\FundingGroups;
use VincentAuger\OrcidSdk\Data\Fundings\FundingSummary;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewGroups;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewSummary;
use VincentAuger\OrcidSdk\Data\Person\Address;
use VincentAuger\OrcidSdk\Data\Person\Biography;
use VincentAuger\OrcidSdk\Data\Person\Email;
use VincentAuger\OrcidSdk\Data\Person\Keyword;
use VincentAuger\OrcidSdk\Data\Person\OtherName;
use VincentAuger\OrcidSdk\Data\Person\Person;
use VincentAuger\OrcidSdk\Data\Person\PersonalDetails;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifier;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifierInput;
use VincentAuger\OrcidSdk\Data\Person\PersonName;
use VincentAuger\OrcidSdk\Data\Person\ResearcherUrl;
use VincentAuger\OrcidSdk\Data\Record\ActivityCounts;
use VincentAuger\OrcidSdk\Data\Record\Record;
use VincentAuger\OrcidSdk\Data\Record\RecordSummary;
use VincentAuger\OrcidSdk\Data\Record\RecordSummaryAffiliation;
use VincentAuger\OrcidSdk\Data\Record\RecordSummaryExternalIdentifier;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceGroups;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceSummary;
use VincentAuger\OrcidSdk\Data\Works\WorkGroups;
use VincentAuger\OrcidSdk\Data\Works\WorkSummary;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Enums\Environment;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Resources\ReadOnlyAffiliationsResource;

/*
 * Recorded against the ORCID sandbox record, which only holds test content.
 * Re-recording against a record with other content means updating the values below.
 * Tests with several fixtures are a chain: to re-record, delete the whole folder.
 */

it('reads the person section of its own record through the Member API, as typed items', function (): void {
    $mockClient = new MockClient(testPersonItemFixtures('record/person-member', 'person'));
    $orcid = $this->getMemberApiClient()->withMockClient($mockClient);

    [$person, $putCodes] = withTestPersonItems($orcid, 'person', fn (array $putCodes): array => [$orcid->person(), $putCodes]);

    // the hand-made content (issue #3): name, credit name, other name, biography, website and country
    expect($person->name?->givenNames)->toBe('Vincent')
        ->and($person->name?->familyName)->toBe('Auger')
        ->and($person->name?->creditName)->toBe('V JP Auger')
        ->and($person->name?->visibility)->toBe(Visibility::Public)
        ->and(array_map(fn (OtherName $name): string => $name->content, $person->otherNames))->toContain('JP Auger')
        ->and($person->biography?->content)->toBe('A test bio.')
        ->and(array_map(fn (ResearcherUrl $url): string => $url->url, $person->researcherUrls))->toContain('https://dfo-mpo.gc.ca')
        ->and(array_map(fn (Address $address): string => $address->country, $person->addresses))->toContain('CA')
        ->and($person->lastModifiedAt)->toBeInstanceOf(DateTimeImmutable::class);

    // the test items: the same classes as each section's own list()
    $ours = static fn (array $items, int $putCode): array => array_values(array_filter($items, fn (object $item): bool => $item->putCode === $putCode));

    expect($ours($person->keywords, $putCodes['keyword'])[0]->content)->toBe('orcid-php-sdk person')
        ->and($ours($person->otherNames, $putCodes['otherName'])[0]->content)->toBe('orcid-php-sdk person')
        ->and($ours($person->researcherUrls, $putCodes['researcherUrl'])[0]->name)->toBe('orcid-php-sdk person')
        ->and($ours($person->externalIdentifiers, $putCodes['externalIdentifier'])[0]->externalId->value)->toBe('orcid-php-sdk-person')
        ->and($ours($person->addresses, $putCodes['address'])[0]->country)->toBe('AQ')
        ->and($this->isOurs($ours($person->addresses, $putCodes['address'])[0]->source))->toBeTrue();

    // /read-limited also returns limited items, like the email, which the researcher added
    expect($person->emails)->toHaveCount(1)
        ->and($person->emails[0]->email)->toBe('vincentauger@mailinator.com')
        ->and($person->emails[0]->visibility)->toBe(Visibility::Limited)
        ->and($person->emails[0]->source?->isRecordOwner())->toBeTrue()
        ->and($person->emails[0]->source?->isClient(OrcidFixture::PLACEHOLDER_CLIENT_ID))->toBeFalse()
        ->and($person->emails[0]->source?->assertionOriginClientId)->toBeNull();
});

it('reads the person section through the Public API without a token', function (): void {
    $mockClient = new MockClient(testPersonItemFixtures('record/person-public', 'person'));
    $owner = $this->getMemberApiClient()->withMockClient($mockClient);
    $record = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId());

    [$person, $putCodes] = withTestPersonItems($owner, 'public person', fn (array $putCodes): array => [$record->person(), $putCodes]);

    // public items only: the limited email is not returned
    $keywords = array_map(fn (Keyword $keyword): int => $keyword->putCode, $person->keywords);
    $identifiers = array_map(fn (PersonExternalIdentifier $id): int => $id->putCode, $person->externalIdentifiers);

    expect($keywords)->toContain($putCodes['keyword'])
        ->and($identifiers)->toContain($putCodes['externalIdentifier'])
        ->and($person->addresses[0]->visibility)->toBe(Visibility::Public)
        ->and($person->emails)->toBeEmpty();
});

it('reads the biography alone, as the same class as person()', function (): void {
    $mockClient = new MockClient([
        new OrcidFixture('record/biography/member'),
        new OrcidFixture('record/biography/public'),
    ]);

    $own = $this->getMemberApiClient()->withMockClient($mockClient)->biography();
    $public = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->biography();

    // the hand-made biography (issue #3) is public: both APIs return it
    expect($own)->toBeInstanceOf(Biography::class)
        ->and($own?->content)->toBe('A test bio.')
        ->and($own?->visibility)->toBe(Visibility::Public)
        ->and($public?->content)->toBe('A test bio.');
});

it('reads the emails alone, as the same class as person()', function (): void {
    $mockClient = new MockClient([
        new OrcidFixture('record/emails/member'),
        new OrcidFixture('record/emails/public'),
    ]);

    $own = $this->getMemberApiClient()->withMockClient($mockClient)->emails();
    $public = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->emails();

    // the researcher's email is limited: /read-limited returns it, the Public API doesn't
    expect($own)->toHaveCount(1)
        ->and($own[0])->toBeInstanceOf(Email::class)
        ->and($own[0]->email)->toBe('vincentauger@mailinator.com')
        ->and($own[0]->primary)->toBeTrue()
        ->and($own[0]->visibility)->toBe(Visibility::Limited)
        ->and($own[0]->source?->isRecordOwner())->toBeTrue()
        ->and($public)->toBe([]);
});

it('reads the personal details: the name, other names and biography, as the same classes as person()', function (): void {
    $mockClient = new MockClient([
        new OrcidFixture('record/personal-details/member'),
        new OrcidFixture('record/personal-details/public'),
    ]);

    $own = $this->getMemberApiClient()->withMockClient($mockClient)->personalDetails();
    $public = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->personalDetails();

    // the hand-made content (issue #3), all public: both APIs return it
    foreach ([$own, $public] as $details) {
        expect($details)->toBeInstanceOf(PersonalDetails::class)
            ->and($details->name)->toBeInstanceOf(PersonName::class)
            ->and($details->name?->displayName())->toBe('V JP Auger')
            ->and($details->otherNames[0])->toBeInstanceOf(OtherName::class)
            ->and($details->otherNames[0]->content)->toBe('JP Auger')
            ->and($details->otherNames[0]->source?->isRecordOwner())->toBeTrue()
            ->and($details->biography)->toBeInstanceOf(Biography::class)
            ->and($details->biography?->content)->toBe('A test bio.')
            ->and($details->lastModifiedAt)->toBeInstanceOf(DateTimeImmutable::class);
    }
});

/**
 * The test items of withTestActivities() in an activities summary, as the same classes as each section's own list()
 *
 * @param  array{work: int, funding: int, peerReview: int, researchResource: int, service: int}  $putCodes
 */
function expectTestActivities(ActivitiesSummary $activities, array $putCodes, string $label): void
{
    $ours = static fn (array $summaries, int $putCode): array => array_values(array_filter($summaries, fn (object $summary): bool => $summary->putCode === $putCode));
    $marked = OrcidFixture::TEST_MARKER.' '.$label;

    expect($activities->works)->toBeInstanceOf(WorkGroups::class)
        ->and($ours($activities->works->summaries(), $putCodes['work'])[0])->toBeInstanceOf(WorkSummary::class)
        ->and($ours($activities->works->summaries(), $putCodes['work'])[0]->title?->title)->toBe($marked)
        ->and($activities->fundings)->toBeInstanceOf(FundingGroups::class)
        ->and($ours($activities->fundings->summaries(), $putCodes['funding'])[0])->toBeInstanceOf(FundingSummary::class)
        ->and($ours($activities->fundings->summaries(), $putCodes['funding'])[0]->title?->title)->toBe($marked)
        ->and($activities->peerReviews)->toBeInstanceOf(PeerReviewGroups::class)
        ->and($ours($activities->peerReviews->summaries(), $putCodes['peerReview'])[0])->toBeInstanceOf(PeerReviewSummary::class)
        ->and($ours($activities->peerReviews->summaries(), $putCodes['peerReview'])[0]->reviewGroupId)->toBe(TEST_REVIEW_GROUP)
        ->and(array_keys($activities->peerReviews->byReviewGroup()))->toContain(TEST_REVIEW_GROUP)
        ->and($activities->researchResources)->toBeInstanceOf(ResearchResourceGroups::class)
        ->and($ours($activities->researchResources->summaries(), $putCodes['researchResource'])[0])->toBeInstanceOf(ResearchResourceSummary::class)
        ->and($ours($activities->researchResources->summaries(), $putCodes['researchResource'])[0]->proposal?->title?->title)->toBe($marked)
        ->and($ours($activities->affiliations(AffiliationType::Service), $putCodes['service'])[0]->departmentName)->toBe($marked)
        // the counts still count ORCID's groups: at least the test item's
        ->and($activities->workCount())->toBeGreaterThanOrEqual(1)
        ->and($activities->fundingCount())->toBeGreaterThanOrEqual(1)
        ->and($activities->peerReviewCount())->toBeGreaterThanOrEqual(1)
        ->and($activities->researchResourceCount())->toBeGreaterThanOrEqual(1)
        ->and($activities->lastModifiedAt)->toBeInstanceOf(DateTimeImmutable::class);
}

it('summarises the activities of its own record, with the typed summaries of every section', function (): void {
    $mockClient = new MockClient(testActivityFixtures('record/activities', new OrcidFixture('record/activities/summary')));
    $orcid = $this->getMemberApiClient()->withMockClient($mockClient);

    [$activities, $putCodes] = withTestActivities($orcid, 'activities', fn (array $putCodes): array => [$orcid->activities(), $putCodes]);

    expectTestActivities($activities, $putCodes, 'activities');
});

it('reads the whole record of its own record: the person and activities sections in one call', function (): void {
    $folder = 'record/whole-member';
    $mockClient = new MockClient(testActivityFixtures($folder, ...testPersonItemFixtures($folder, 'record')));
    $orcid = $this->getMemberApiClient()->withMockClient($mockClient);

    [$record, $activityPutCodes, $personPutCodes] = withTestActivities($orcid, 'whole record', fn (array $activityPutCodes): array => withTestPersonItems(
        $orcid,
        'whole record',
        fn (array $personPutCodes): array => [$orcid->wholeRecord(), $activityPutCodes, $personPutCodes],
    ));

    expect($record)->toBeInstanceOf(Record::class)
        ->and($record->orcid)->toBeIn($this->recordedIds())
        ->and($record->person)->toBeInstanceOf(Person::class)
        ->and($record->person->name?->familyName)->toBe('Auger')
        ->and(array_map(fn (Keyword $keyword): int => $keyword->putCode, $record->person->keywords))->toContain($personPutCodes['keyword'])
        ->and(array_map(fn (Address $address): int => $address->putCode, $record->person->addresses))->toContain($personPutCodes['address'])
        // /read-limited: the limited email too
        ->and($record->person->emails[0]->visibility)->toBe(Visibility::Limited)
        ->and($record->history?->claimed)->toBeTrue()
        ->and($record->history?->submittedAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($record->history?->lastModifiedAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($record->locale)->toBe('fr');

    expectTestActivities($record->activities, $activityPutCodes, 'whole record');
});

it('reads the whole record of any record, through the Public API', function (): void {
    $mockClient = new MockClient(testActivityFixtures('record/whole-public', new OrcidFixture('record/whole-public/record')));
    $owner = $this->getMemberApiClient()->withMockClient($mockClient);
    $public = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId());

    [$record, $putCodes] = withTestActivities($owner, 'public whole record', fn (array $putCodes): array => [$public->wholeRecord(), $putCodes]);

    // public items only: the limited email is not returned
    expect($record->orcid)->toBeIn($this->recordedIds())
        ->and($record->person->biography?->content)->toBe('A test bio.')
        ->and($record->person->emails)->toBeEmpty();

    expectTestActivities($record->activities, $putCodes, 'public whole record');
});

it('reads the record summary of its own record', function (): void {
    $folder = 'record/summary';
    // one test item in every activity section, so each count has a validated item
    $mockClient = new MockClient(testActivityFixtures(
        $folder,
        new OrcidFixture("{$folder}/create-employment"),
        new OrcidFixture("{$folder}/create-education"),
        new OrcidFixture("{$folder}/create-marked-service"),
        new OrcidFixture("{$folder}/create-external-identifier"),
        new OrcidFixture("{$folder}/summary"),
        new OrcidFixture("{$folder}/delete-employment"),
        new OrcidFixture("{$folder}/delete-education"),
        new OrcidFixture("{$folder}/delete-marked-service"),
        new OrcidFixture("{$folder}/delete-external-identifier"),
    ));
    $orcid = $this->getMemberApiClient()->withMockClient($mockClient);

    // the summary's affiliations have no department name: these mark their role title instead
    $role = static fn (string $label): string => OrcidFixture::TEST_MARKER.' summary '.$label;

    [$summary, $employment, $education, $service, $identifier] = withTestActivities($orcid, 'summary', function () use ($orcid, $role): array {
        $employment = $orcid->affiliations()->create(AffiliationType::Employment, testAffiliation('summary employment', roleTitle: $role('employment')));
        $education = $orcid->affiliations()->create(AffiliationType::Education, testAffiliation('summary education', roleTitle: $role('education')));
        $service = $orcid->affiliations()->create(AffiliationType::Service, testAffiliation('summary service', roleTitle: $role('service')));
        $identifier = $orcid->externalIdentifiers()->create(new PersonExternalIdentifierInput('Scopus Author ID', 'orcid-php-sdk-summary', 'https://example.org/orcid-php-sdk-summary'));

        try {
            return [$orcid->recordSummary(), $employment, $education, $service, $identifier];
        } finally {
            $orcid->affiliations()->delete(AffiliationType::Employment, $employment);
            $orcid->affiliations()->delete(AffiliationType::Education, $education);
            $orcid->affiliations()->delete(AffiliationType::Service, $service);
            $orcid->externalIdentifiers()->delete($identifier);
        }
    });

    $ours = static fn (array $items, int $putCode): ?object => array_values(array_filter($items, fn (object $item): bool => $item->putCode === $putCode))[0] ?? null;
    $ourIdentifier = $ours($summary->externalIdentifiers, $identifier);

    expect($summary)->toBeInstanceOf(RecordSummary::class)
        ->and($summary->orcid)->toBeIn($this->recordedIds())
        ->and($summary->creditName)->toBe('V JP Auger')
        ->and($summary->createdAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($summary->lastModifiedAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($ours($summary->employments, $employment)?->type)->toBe(AffiliationType::Employment)
        ->and($ours($summary->employments, $employment)?->role)->toBe($role('employment'))
        ->and($ours($summary->employments, $employment)?->organizationName)->toBe('Fisheries and Oceans Canada')
        ->and($ours($summary->employments, $employment)?->startDate?->year)->toBe(2021)
        // added by a client: validated, unlike the researcher's own (self-asserted)
        ->and($ours($summary->employments, $employment)?->validated)->toBeTrue()
        ->and($ours($summary->educationQualifications, $education)?->type)->toBe(AffiliationType::Education)
        ->and($ours($summary->professionalActivities, $service)?->type)->toBe(AffiliationType::Service)
        // ORCID's counts include the items fixtures leave out
        ->and($summary->employmentCount)->toBeGreaterThanOrEqual(1)
        ->and($summary->educationQualificationCount)->toBeGreaterThanOrEqual(1)
        ->and($summary->professionalActivityCount)->toBeGreaterThanOrEqual(1)
        ->and($ourIdentifier)->toBeInstanceOf(RecordSummaryExternalIdentifier::class)
        ->and($ourIdentifier?->type)->toBe('Scopus Author ID')
        ->and($ourIdentifier?->value)->toBe('orcid-php-sdk-summary')
        ->and($ourIdentifier?->url)->toBe('https://example.org/orcid-php-sdk-summary')
        ->and($ourIdentifier?->validated)->toBeTrue()
        // the test items are validated; the hand-added work (issue #3) is self-asserted
        ->and($summary->works)->toBeInstanceOf(ActivityCounts::class)
        ->and($summary->works->validated)->toBeGreaterThanOrEqual(1)
        ->and($summary->works->selfAsserted)->toBeGreaterThanOrEqual(1)
        ->and($summary->fundings->validated)->toBeGreaterThanOrEqual(1)
        // peer reviews come as a total and a self-asserted count: the rest is validated
        ->and($summary->peerReviews->validated)->toBeGreaterThanOrEqual(1)
        ->and($summary->peerReviewPublicationGrants)->toBeGreaterThanOrEqual(1)
        ->and($summary->researchResources->validated)->toBeGreaterThanOrEqual(1)
        ->and($summary->emailDomainCount)->toBe(0);
});

it('refuses the record summary on the Public API before sending: ORCID only serves it on the Member API', function (): void {
    // nothing is sent: the c7/ probe shows the Public API answers 404 (error 9001), even with a /read-public token
    $this->getPublicApiClient()->withMockClient(new MockClient([]))->record($this->orcidId())->recordSummary();
})->throws(LogicException::class, 'requires the Member API');

it('only reads other records, through the Public API', function (): void {
    $mockClient = new MockClient([
        new OrcidFixture('record/public-reads/create'),
        new OrcidFixture('record/public-reads/list'),
        new OrcidFixture('record/public-reads/get'),
        new OrcidFixture('record/public-reads/delete'),
    ]);

    $putCode = $this->getMemberApiClient()->withMockClient($mockClient)->affiliations()
        ->create(AffiliationType::Service, testAffiliation('public reads'));

    $affiliations = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->affiliations();
    $listed = $affiliations->list(AffiliationType::Service);
    $read = $affiliations->get(AffiliationType::Service, $putCode);

    $this->getMemberApiClient()->withMockClient($mockClient)->affiliations()->delete(AffiliationType::Service, $putCode);

    expect($affiliations)->toBeInstanceOf(ReadOnlyAffiliationsResource::class)
        ->and(method_exists($affiliations, 'create'))->toBeFalse()
        ->and(array_map(fn (Affiliation $affiliation): int => $affiliation->putCode, $listed))->toContain($putCode)
        ->and($read->departmentName)->toBe(OrcidFixture::TEST_MARKER.' public reads')
        ->and($read->visibility)->toBe(Visibility::Public);
});

it('reads a record through the Member API with a /read-public token, without binding a record', function (): void {
    // the token request, the reads and the revoke are a chain: to re-record, delete the whole folder
    $folder = 'record/read-public';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/client-credentials"),
        new OrcidFixture("{$folder}/person"),
        new OrcidFixture("{$folder}/activities"),
        new OrcidFixture("{$folder}/summary"),
        new OrcidFixture("{$folder}/services"),
        new OrcidFixture("{$folder}/revoke"),
        new OrcidFixture("{$folder}/delete"),
    ]);

    $owner = $this->getMemberApiClient()->withMockClient($mockClient);
    // a marked role title: the record summary's affiliations have no department name
    $putCode = $owner->affiliations()->create(AffiliationType::Service, testAffiliation('read public', roleTitle: OrcidFixture::TEST_MARKER.' read public'));

    $oauth = $this->getOAuthClient()->withMockClient($mockClient);
    $token = $oauth->clientCredentials([Scope::ReadPublic]);

    $record = Orcid::member(accessToken: $token->accessToken, environment: Environment::Sandbox)
        ->withMockClient($mockClient)
        ->record($this->orcidId());
    $person = $record->person();
    $activities = $record->activities();
    // the record summary needs the Member API, not a bound record: a /read-public token reads any record's
    $summary = $record->recordSummary();
    $listed = $record->affiliations()->list(AffiliationType::Service);

    $oauth->revoke($token->accessToken);
    $owner->affiliations()->delete(AffiliationType::Service, $putCode);

    $services = $activities->affiliations(AffiliationType::Service);

    // public items only: the limited email seen with /read-limited is not returned
    expect($person->name?->familyName)->toBe('Auger')
        ->and($person->emails)->toBeEmpty()
        ->and(array_map(fn (Affiliation $affiliation): int => $affiliation->putCode, $services))->toContain($putCode)
        ->and($services[0]->visibility)->toBe(Visibility::Public)
        ->and($summary->orcid)->toBeIn($this->recordedIds())
        ->and(array_map(fn (RecordSummaryAffiliation $item): int => $item->putCode, $summary->professionalActivities))->toContain($putCode)
        ->and($record->affiliations())->toBeInstanceOf(ReadOnlyAffiliationsResource::class)
        ->and(array_map(fn (Affiliation $affiliation): int => $affiliation->putCode, $listed))->toContain($putCode);
});
