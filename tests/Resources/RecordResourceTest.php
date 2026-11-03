<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Data\Person\Keyword;
use VincentAuger\OrcidSdk\Data\Person\KeywordInput;
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
    $folder = 'record/person-member';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create-keyword"),
        new OrcidFixture("{$folder}/person"),
        new OrcidFixture("{$folder}/delete-keyword"),
    ]);

    $orcid = $this->getMemberApiClient()->withMockClient($mockClient);
    $putCode = $orcid->keywords()->create(new KeywordInput(OrcidFixture::TEST_MARKER.' person'));

    try {
        $person = $orcid->person();
    } finally {
        $orcid->keywords()->delete($putCode);
    }

    expect($person->name?->givenNames)->toBe('Vincent')
        ->and($person->name?->familyName)->toBe('Auger')
        ->and($person->name?->visibility)->toBe(Visibility::Public)
        ->and($person->otherNames)->toHaveCount(1)
        ->and($person->otherNames[0]->content)->toBe('JP Auger')
        ->and($person->otherNames[0]->putCode)->toBe(19146)
        ->and($person->otherNames[0]->visibility)->toBe(Visibility::Public)
        ->and($person->otherNames[0]->displayIndex)->toBe(2)
        ->and($person->biography?->content)->toBe('A test bio.')
        ->and($person->biography?->visibility)->toBe(Visibility::Public)
        ->and($person->researcherUrls)->toHaveCount(1)
        ->and($person->researcherUrls[0]->name)->toBe('DFO')
        ->and($person->researcherUrls[0]->url)->toBe('https://dfo-mpo.gc.ca')
        ->and($person->researcherUrls[0]->putCode)->toBe(48660)
        ->and($person->researcherUrls[0]->visibility)->toBe(Visibility::Public)
        ->and($person->researcherUrls[0]->source?->isRecordOwner())->toBeTrue()
        ->and($person->addresses)->toHaveCount(1)
        ->and($person->addresses[0]->country)->toBe('CA')
        ->and($person->addresses[0]->putCode)->toBe(8230)
        ->and($person->lastModifiedAt)->toBeInstanceOf(DateTimeImmutable::class);

    // the same Keyword as keywords()->list(); the fixture only keeps the test keyword, not the researcher's own
    $ours = array_values(array_filter($person->keywords, fn (Keyword $keyword): bool => $keyword->putCode === $putCode));

    expect($ours)->toHaveCount(1)
        ->and($ours[0]->content)->toBe('orcid-php-sdk person')
        ->and($ours[0]->visibility)->toBe(Visibility::Public)
        ->and($this->isOurs($ours[0]->source))->toBeTrue();

    // /read-limited also returns limited items, like the email, which the researcher added
    expect($person->emails)->toHaveCount(1)
        ->and($person->emails[0]->email)->toBe('vincentauger@mailinator.com')
        ->and($person->emails[0]->visibility)->toBe(Visibility::Limited)
        ->and($person->emails[0]->source?->isRecordOwner())->toBeTrue()
        ->and($person->emails[0]->source?->isClient(OrcidFixture::PLACEHOLDER_CLIENT_ID))->toBeFalse()
        ->and($person->emails[0]->source?->assertionOriginClientId)->toBeNull();
});

it('reads the person section through the Public API without a token', function (): void {
    $folder = 'record/person-public';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create-keyword"),
        new OrcidFixture("{$folder}/person"),
        new OrcidFixture("{$folder}/delete-keyword"),
    ]);

    $owner = $this->getMemberApiClient()->withMockClient($mockClient);
    $putCode = $owner->keywords()->create(new KeywordInput(OrcidFixture::TEST_MARKER.' public person'));

    try {
        $person = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->person();
    } finally {
        $owner->keywords()->delete($putCode);
    }

    // public items only: the limited email is not returned
    $ours = array_values(array_filter($person->keywords, fn (Keyword $keyword): bool => $keyword->putCode === $putCode));

    expect($ours)->toHaveCount(1)
        ->and($ours[0]->visibility)->toBe(Visibility::Public)
        ->and($person->addresses[0]->putCode)->toBe(8230)
        ->and($person->addresses[0]->visibility)->toBe(Visibility::Public)
        ->and($person->emails)->toBeEmpty();
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
