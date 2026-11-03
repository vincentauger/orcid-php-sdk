<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Response;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Common\Contributor;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\TranslatedTitle;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Works\Citation;
use VincentAuger\OrcidSdk\Data\Works\Work;
use VincentAuger\OrcidSdk\Data\Works\WorkSummary;
use VincentAuger\OrcidSdk\Enums\CitationType;
use VincentAuger\OrcidSdk\Enums\ContributorRole;
use VincentAuger\OrcidSdk\Enums\ContributorSequence;
use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Enums\WorkType;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Works\CreateWork;

/*
 * Recorded against the ORCID sandbox. Every test only touches works it created
 * (titles start with OrcidFixture::TEST_MARKER) and deletes them.
 * Each test's fixtures are a chain: to re-record, delete the whole folder.
 */

it('creates, reads, updates and deletes a work', function (): void {
    $folder = 'works/lifecycle';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/get"),
        new OrcidFixture("{$folder}/get-public"),
        new OrcidFixture("{$folder}/update"),
        new OrcidFixture("{$folder}/delete"),
        new OrcidFixture("{$folder}/get-deleted"),
    ]);

    // with known scopes, they are checked before sending: the token has them all
    $works = $this->getMemberApiClient(Scope::completeAccess())->withMockClient($mockClient)->works();

    $putCode = $works->create(testWork(
        'lifecycle',
        title: new Title(OrcidFixture::TEST_MARKER.' lifecycle', 'A subtitle', new TranslatedTitle('Cycle de vie', 'fr')),
        externalIds: [
            new ExternalId('other-id', OrcidFixture::TEST_MARKER.'-lifecycle'),
            new ExternalId('issn', '1234-5679', ExternalIdRelationship::PartOf),
        ],
        journalTitle: 'Journal of SDK Testing',
        shortDescription: 'A work created by the SDK tests',
        citation: new Citation(CitationType::Bibtex, '@article{lifecycle, title={orcid-php-sdk lifecycle}}'),
        publicationDate: FuzzyDate::fromString('2021-03'),
        url: new Url('https://example.org/orcid-php-sdk/lifecycle'),
        contributors: [
            new Contributor('Test Author', $this->orcidId(), ContributorRole::Author, ContributorSequence::First),
            new Contributor('Second Author', role: ContributorRole::Software, sequence: ContributorSequence::Additional),
        ],
        languageCode: 'en',
        country: 'CA',
    ));

    expect($putCode)->toBeGreaterThan(0);

    $created = $works->get($putCode);

    expect($created->putCode)->toBe($putCode)
        ->and($created->title?->title)->toBe('orcid-php-sdk lifecycle')
        ->and($created->title?->subtitle)->toBe('A subtitle')
        ->and($created->title?->translatedTitle?->title)->toBe('Cycle de vie')
        ->and($created->title?->translatedTitle?->languageCode)->toBe('fr')
        ->and($created->type)->toBe(WorkType::JournalArticle)
        ->and($created->rawType)->toBe('journal-article')
        ->and($created->journalTitle)->toBe('Journal of SDK Testing')
        ->and($created->shortDescription)->toBe('A work created by the SDK tests')
        ->and($created->citation?->type)->toBe(CitationType::Bibtex)
        ->and($created->citation?->value)->toBe('@article{lifecycle, title={orcid-php-sdk lifecycle}}')
        ->and((string) $created->publicationDate)->toBe('2021-03')
        ->and((string) $created->url)->toBe('https://example.org/orcid-php-sdk/lifecycle')
        ->and($created->externalIds)->toHaveCount(2)
        ->and($created->externalIds[0]->value)->toBe('orcid-php-sdk-lifecycle')
        ->and($created->externalIds[0]->relationship)->toBe(ExternalIdRelationship::Self)
        ->and($created->externalIds[1]->relationship)->toBe(ExternalIdRelationship::PartOf)
        ->and($created->contributors)->toHaveCount(2)
        // the placeholder once replayed, the sandbox record while recording
        ->and($created->contributors[0]->orcid)->toBeIn([OrcidFixture::PLACEHOLDER_ID, $this->orcidId()])
        ->and($created->contributors[0]->creditName)->toBe('Test Author')
        ->and($created->contributors[0]->role)->toBe(ContributorRole::Author)
        ->and($created->contributors[0]->sequence)->toBe(ContributorSequence::First)
        ->and($created->contributors[1]->orcid)->toBeNull()
        ->and($created->contributors[1]->role)->toBe(ContributorRole::Software)
        ->and($created->languageCode)->toBe('en')
        ->and($created->country)->toBe('CA')
        ->and($this->isOurs($created->source))->toBeTrue()
        ->and($created->source?->isClient('APP-SOMEONEELSE00000'))->toBeFalse()
        ->and($created->createdAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($created->visibility)->toBe(Visibility::Public);

    // every connector reads public works through record($id)
    $public = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->works()->get($putCode);

    expect($public->title?->title)->toBe('orcid-php-sdk lifecycle')
        ->and($this->isOurs($public->source))->toBeTrue();

    // PUT replaces the whole item: the fields left out are cleared
    $updated = $works->update($putCode, testWork('lifecycle', type: WorkType::Preprint));

    expect($updated->putCode)->toBe($putCode)
        ->and($updated->type)->toBe(WorkType::Preprint)
        ->and($updated->title?->subtitle)->toBeNull()
        ->and($updated->journalTitle)->toBeNull()
        ->and($updated->citation)->toBeNull()
        ->and($updated->contributors)->toBe([])
        ->and($updated->externalIds)->toHaveCount(1);

    $works->delete($putCode);

    expect(fn (): Work => $works->get($putCode))->toThrow(OrcidNotFoundException::class);
});

it('refuses a second work with the same self identifier', function (): void {
    $folder = 'works/duplicate';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/create-again"),
        new OrcidFixture("{$folder}/delete"),
    ]);

    $works = $this->getMemberApiClient()->withMockClient($mockClient)->works();

    $putCode = $works->create(testWork('duplicate'));

    // caught before the cleanup, so a failed assertion never leaves the work behind
    try {
        $works->create(testWork('duplicate', type: WorkType::Preprint));
        $conflict = null;
    } catch (OrcidConflictException $exception) {
        $conflict = $exception;
    }

    $works->delete($putCode);

    expect($conflict)->toBeInstanceOf(OrcidConflictException::class)
        ->and($conflict?->status())->toBe(409)
        ->and($conflict?->errorCode)->toBe(9021)
        ->and($conflict?->isDuplicate())->toBeTrue()
        ->and($conflict?->existingPutCode())->toBe($putCode);
});

it('lists works as groups, with the preferred summary and every summary', function (): void {
    $folder = 'works/list';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/list"),
        new OrcidFixture("{$folder}/list-public"),
        new OrcidFixture("{$folder}/delete"),
    ]);

    $works = $this->getMemberApiClient()->withMockClient($mockClient)->works();

    // the same DOI as the work added by hand on the sandbox record (in another form), so ORCID groups the two
    $putCode = $works->create(testWork('list', externalIds: [new ExternalId('doi', 'https://doi.org/'.strtoupper(HAND_ADDED_DOI))]));

    // read before the cleanup, asserted after it, so a failed assertion never leaves the work behind
    $list = $works->list();
    $public = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->works()->list();

    $works->delete($putCode);

    // the fixture only keeps the group holding the test work, with the hand-added work in it
    expect($list->groups)->toHaveCount(1);

    $group = $list->groups[0];
    [$ours, $handAdded] = $this->isOurs($group->summaries[0]->source) ? $group->summaries : array_reverse($group->summaries);
    // recorded before the researcher chose the hand-added work as preferred on the website (works/preferred): until
    // then it keeps display index 0, and a client's work (1) is the preferred one; afterwards it holds 2
    $preferred = ($handAdded->displayIndex ?? 0) > 1 ? $handAdded : $ours;

    expect($group->summaries)->toHaveCount(2)
        ->and($group->externalIds)->toHaveCount(1)
        ->and($group->externalIds[0]->type)->toBe('doi')
        ->and($group->externalIds[0]->normalizedValue)->toBe(HAND_ADDED_DOI)
        ->and($ours->putCode)->toBe($putCode)
        ->and($this->isOurs($ours->source))->toBeTrue()
        ->and($ours->displayIndex)->toBe(1)
        ->and($ours->title?->title)->toBe('orcid-php-sdk list')
        ->and($ours->type)->toBe(WorkType::JournalArticle)
        ->and($ours->externalIds[0]->value)->toBe('https://doi.org/'.strtoupper(HAND_ADDED_DOI))
        ->and($ours->externalIds[0]->normalizedValue)->toBe(HAND_ADDED_DOI)
        ->and($ours->visibility)->toBe(Visibility::Public)
        ->and($handAdded->source?->isRecordOwner())->toBeTrue()
        ->and($handAdded->displayIndex)->toBeIn([0, 2])
        ->and($handAdded->title?->title)->toBe('Current and future trends in marine image annotation software')
        ->and($handAdded->journalTitle)->toBe('Progress in Oceanography')
        ->and((string) $handAdded->publicationDate)->toBe('2016-12')
        ->and(array_map(fn (ExternalId $id): ?string => $id->normalizedValue, $handAdded->externalIds))->toBe([HAND_ADDED_DOI, '0079-6611'])
        // ORCID lists the preferred summary first
        ->and($group->preferred())->toBe($preferred)
        ->and($group->summaries[0])->toBe($preferred)
        ->and($list->preferred())->toBe([$preferred])
        ->and($list->summaries())->toBe($group->summaries)
        ->and(array_map(fn (WorkSummary $summary): int => $summary->putCode, $public->summaries()))
        ->toBe(array_map(fn (WorkSummary $summary): int => $summary->putCode, $group->summaries));
});

/*
 * The researcher's choice can only be made on the ORCID website. To re-record: delete the folder, add a work titled
 * "orcid-php-sdk preferred" with the DOI HAND_ADDED_DOI through the API, choose the hand-added work as preferred
 * on the sandbox website, then run this test: it deletes the test work.
 */
it('prefers the summary the researcher chose', function (): void {
    $folder = 'works/preferred';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/list"),
        new OrcidFixture("{$folder}/delete"),
    ]);

    $works = $this->getMemberApiClient()->withMockClient($mockClient)->works();

    $list = $works->list();
    $ours = array_values(array_filter($list->summaries(), fn (WorkSummary $summary): bool => $this->isOurs($summary->source)));

    if ($ours !== []) {
        $works->delete($ours[0]->putCode);
    }

    $group = $list->groups[0];

    expect($ours)->toHaveCount(1)
        ->and($ours[0]->title?->title)->toBe('orcid-php-sdk preferred')
        ->and($group->summaries)->toHaveCount(2)
        // choosing a summary gives it the highest display index: ORCID lists it first and takes the group's ids from it
        ->and($group->preferred()->source?->isRecordOwner())->toBeTrue()
        ->and($group->preferred()->displayIndex)->toBe(2)
        ->and($group->preferred())->toBe($group->summaries[0])
        ->and($ours[0]->displayIndex)->toBe(1)
        ->and($group->externalIds[0]->value)->toBe(HAND_ADDED_DOI);
});

it('checks the token scopes and the API before sending', function (): void {
    $mockClient = new MockClient([]);

    $readOnly = Orcid::member(accessToken: 'token', orcid: '0000-0002-1825-0097', scopes: [Scope::ReadLimited])->withMockClient($mockClient);

    expect(fn (): int => $readOnly->works()->create(testWork('scope')))
        ->toThrow(MissingScopeException::class)
        ->and(fn (): Response => Orcid::public()->withMockClient($mockClient)->send(new CreateWork('0000-0002-1825-0097', testWork('public'))))
        ->toThrow(LogicException::class, 'requires the Member API')
        ->and(fn (): mixed => Orcid::member(accessToken: 'token')->works())
        ->toThrow(LogicException::class, 'bound to a record');

    $mockClient->assertNothingSent();
});
