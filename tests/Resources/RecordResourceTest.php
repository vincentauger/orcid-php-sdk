<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Data\Person\Address;
use VincentAuger\OrcidSdk\Data\Person\Biography;
use VincentAuger\OrcidSdk\Data\Person\Email;
use VincentAuger\OrcidSdk\Data\Person\Keyword;
use VincentAuger\OrcidSdk\Data\Person\OtherName;
use VincentAuger\OrcidSdk\Data\Person\PersonalDetails;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifier;
use VincentAuger\OrcidSdk\Data\Person\PersonName;
use VincentAuger\OrcidSdk\Data\Person\ResearcherUrl;
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

it('summarises the activities of its own record', function (): void {
    $mockClient = new MockClient([
        new OrcidFixture('record/activities/create'),
        new OrcidFixture('record/activities/summary'),
        new OrcidFixture('record/activities/delete'),
    ]);

    $orcid = $this->getMemberApiClient()->withMockClient($mockClient);

    $putCode = $orcid->affiliations()->create(AffiliationType::Service, testAffiliation('activities'));

    $summary = $orcid->activities();

    $orcid->affiliations()->delete(AffiliationType::Service, $putCode);

    $services = array_map(fn (Affiliation $affiliation): int => $affiliation->putCode, $summary->affiliations(AffiliationType::Service));

    expect($services)->toContain($putCode)
        ->and($summary->workCount)->toBeGreaterThanOrEqual(0)
        ->and($summary->lastModifiedAt)->toBeInstanceOf(DateTimeImmutable::class);
});

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
        new OrcidFixture("{$folder}/services"),
        new OrcidFixture("{$folder}/revoke"),
        new OrcidFixture("{$folder}/delete"),
    ]);

    $owner = $this->getMemberApiClient()->withMockClient($mockClient);
    $putCode = $owner->affiliations()->create(AffiliationType::Service, testAffiliation('read public'));

    $oauth = $this->getOAuthClient()->withMockClient($mockClient);
    $token = $oauth->clientCredentials([Scope::ReadPublic]);

    $record = Orcid::member(accessToken: $token->accessToken, environment: Environment::Sandbox)
        ->withMockClient($mockClient)
        ->record($this->orcidId());
    $person = $record->person();
    $activities = $record->activities();
    $listed = $record->affiliations()->list(AffiliationType::Service);

    $oauth->revoke($token->accessToken);
    $owner->affiliations()->delete(AffiliationType::Service, $putCode);

    $services = $activities->affiliations(AffiliationType::Service);

    // public items only: the limited email seen with /read-limited is not returned
    expect($person->name?->familyName)->toBe('Auger')
        ->and($person->emails)->toBeEmpty()
        ->and(array_map(fn (Affiliation $affiliation): int => $affiliation->putCode, $services))->toContain($putCode)
        ->and($services[0]->visibility)->toBe(Visibility::Public)
        ->and($record->affiliations())->toBeInstanceOf(ReadOnlyAffiliationsResource::class)
        ->and(array_map(fn (Affiliation $affiliation): int => $affiliation->putCode, $listed))->toContain($putCode);
});
