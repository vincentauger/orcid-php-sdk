<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Response;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\TranslatedTitle;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResource;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceItemInput;
use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Enums\ResearchResourceType;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\ResearchResources\CreateResearchResource;

/*
 * Recorded against the ORCID sandbox. Every test only touches research resources it created
 * (proposal titles start with OrcidFixture::TEST_MARKER) and deletes them.
 * Each test's fixtures are a chain: to re-record, delete the whole folder.
 */

it('creates, reads, updates, lists and deletes a research resource', function (): void {
    $folder = 'research-resources/lifecycle';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/get"),
        new OrcidFixture("{$folder}/get-public"),
        new OrcidFixture("{$folder}/update"),
        new OrcidFixture("{$folder}/list"),
        new OrcidFixture("{$folder}/list-public"),
        new OrcidFixture("{$folder}/delete"),
        new OrcidFixture("{$folder}/get-deleted"),
    ]);

    // with known scopes, they are checked before sending: the token has them all
    $researchResources = $this->getMemberApiClient(Scope::completeAccess())->withMockClient($mockClient)->researchResources();

    $host = new Organization('Fisheries and Oceans Canada', new OrganizationAddress('Ottawa', 'CA', 'ON'), DisambiguatedOrganization::ror('https://ror.org/02qa1x782'));
    $secondHost = new Organization('Canadian Coast Guard', new OrganizationAddress(null, 'CA'), DisambiguatedOrganization::ror('https://ror.org/02qa1x782'));

    $putCode = $researchResources->create(testResearchResource(
        'lifecycle',
        items: [
            new ResearchResourceItemInput(
                OrcidFixture::TEST_MARKER.' vessel',
                ResearchResourceType::Infrastructures,
                [$host, $secondHost],
                [new ExternalId('source-work-id', OrcidFixture::TEST_MARKER.'-lifecycle-vessel', url: 'https://example.org/orcid-php-sdk/vessel')],
                new Url('https://example.org/orcid-php-sdk/vessel'),
            ),
            new ResearchResourceItemInput(
                OrcidFixture::TEST_MARKER.' rosette',
                ResearchResourceType::Equipment,
                [$host],
                [new ExternalId('source-work-id', OrcidFixture::TEST_MARKER.'-lifecycle-rosette', ExternalIdRelationship::PartOf)],
            ),
        ],
        title: new Title(OrcidFixture::TEST_MARKER.' lifecycle', translatedTitle: new TranslatedTitle('Cycle de vie', 'fr')),
        externalIds: [
            new ExternalId('proposal-id', OrcidFixture::TEST_MARKER.'-lifecycle', url: 'https://example.org/orcid-php-sdk/proposals/lifecycle'),
            new ExternalId('proposal-id', OrcidFixture::TEST_MARKER.'-lifecycle-programme', ExternalIdRelationship::PartOf),
        ],
        startDate: FuzzyDate::fromString('2021-07-01'),
        endDate: FuzzyDate::fromString('2021-08'),
        url: new Url('https://example.org/orcid-php-sdk/lifecycle'),
    ));

    expect($putCode)->toBeGreaterThan(0);

    $created = $researchResources->get($putCode);

    expect($created->putCode)->toBe($putCode)
        ->and($created->proposal?->title?->title)->toBe('orcid-php-sdk lifecycle')
        ->and($created->proposal?->title?->translatedTitle?->title)->toBe('Cycle de vie')
        ->and($created->proposal?->hosts)->toHaveCount(1)
        ->and($created->proposal?->hosts[0]->name)->toBe('Fisheries and Oceans Canada')
        ->and($created->proposal?->hosts[0]->disambiguatedOrganization?->identifier)->toBe('https://ror.org/02qa1x782')
        ->and($created->proposal?->externalIds)->toHaveCount(2)
        ->and($created->proposal?->externalIds[0]->value)->toBe('orcid-php-sdk-lifecycle')
        ->and($created->proposal?->externalIds[0]->url)->toBe('https://example.org/orcid-php-sdk/proposals/lifecycle')
        ->and($created->proposal?->externalIds[1]->relationship)->toBe(ExternalIdRelationship::PartOf)
        ->and((string) $created->proposal?->startDate)->toBe('2021-07-01')
        ->and((string) $created->proposal?->endDate)->toBe('2021-08')
        ->and((string) $created->proposal?->url)->toBe('https://example.org/orcid-php-sdk/lifecycle')
        ->and($created->items)->toHaveCount(2)
        ->and($created->items[0]->name)->toBe('orcid-php-sdk vessel')
        ->and($created->items[0]->type)->toBe(ResearchResourceType::Infrastructures)
        ->and($created->items[0]->rawType)->toBe('infrastructures')
        ->and($created->items[0]->hosts)->toHaveCount(2)
        ->and($created->items[0]->hosts[1]->name)->toBe('Canadian Coast Guard')
        ->and($created->items[0]->hosts[1]->address?->city)->toBeNull()
        ->and($created->items[0]->externalIds[0]->value)->toBe('orcid-php-sdk-lifecycle-vessel')
        ->and((string) $created->items[0]->url)->toBe('https://example.org/orcid-php-sdk/vessel')
        ->and($created->items[1]->type)->toBe(ResearchResourceType::Equipment)
        ->and($created->items[1]->externalIds[0]->relationship)->toBe(ExternalIdRelationship::PartOf)
        ->and($created->items[1]->url)->toBeNull()
        ->and($this->isOurs($created->source))->toBeTrue()
        ->and($created->createdAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($created->visibility)->toBe(Visibility::Public);

    // every connector reads public research resources through record($id)
    $public = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->researchResources()->get($putCode);

    expect($public->proposal?->title?->title)->toBe('orcid-php-sdk lifecycle')
        ->and($this->isOurs($public->source))->toBeTrue();

    // PUT replaces the whole item: the fields left out are cleared
    $updated = $researchResources->update($putCode, testResearchResource('lifecycle'));

    expect($updated->putCode)->toBe($putCode)
        ->and($updated->proposal?->title?->translatedTitle)->toBeNull()
        ->and($updated->proposal?->startDate)->toBeNull()
        ->and($updated->proposal?->url)->toBeNull()
        ->and($updated->proposal?->externalIds)->toHaveCount(1)
        ->and($updated->items)->toHaveCount(1)
        ->and($updated->items[0]->externalIds[0]->value)->toBe('orcid-php-sdk-lifecycle-vessel');

    // read before the cleanup, asserted after it, so a failed assertion never leaves the research resource behind
    try {
        $list = $researchResources->list();
        $publicList = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->researchResources()->list();
    } finally {
        $researchResources->delete($putCode);
    }

    // the fixture only keeps the group holding the test research resource
    expect($list->groups)->toHaveCount(1)
        ->and($list->groups[0]->externalIds[0]->value)->toBe('orcid-php-sdk-lifecycle')
        ->and($list->groups[0]->summaries)->toHaveCount(1)
        ->and($list->preferred())->toBe($list->summaries())
        ->and($list->summaries()[0]->putCode)->toBe($putCode)
        ->and($list->summaries()[0]->proposal?->title?->title)->toBe('orcid-php-sdk lifecycle')
        ->and($list->summaries()[0]->proposal?->hosts[0]->name)->toBe('Fisheries and Oceans Canada')
        ->and($list->summaries()[0]->displayIndex)->toBe(1)
        ->and($this->isOurs($list->summaries()[0]->source))->toBeTrue()
        ->and($publicList->summaries()[0]->putCode)->toBe($putCode)
        ->and(fn (): ResearchResource => $researchResources->get($putCode))->toThrow(OrcidNotFoundException::class);
});

it('refuses a second research resource with the same self proposal identifier', function (): void {
    $folder = 'research-resources/duplicate';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/create-again"),
        new OrcidFixture("{$folder}/delete"),
    ]);

    $researchResources = $this->getMemberApiClient()->withMockClient($mockClient)->researchResources();

    $putCode = $researchResources->create(testResearchResource('duplicate'));

    // caught before the cleanup, so a failed assertion never leaves the research resource behind
    try {
        $researchResources->create(testResearchResource('duplicate', url: new Url('https://example.org/orcid-php-sdk/duplicate')));
        $conflict = null;
    } catch (OrcidConflictException $exception) {
        $conflict = $exception;
    } finally {
        $researchResources->delete($putCode);
    }

    expect($conflict)->toBeInstanceOf(OrcidConflictException::class)
        ->and($conflict?->status())->toBe(409)
        ->and($conflict?->errorCode)->toBe(9021)
        ->and($conflict?->isDuplicate())->toBeTrue()
        ->and($conflict?->existingPutCode())->toBe($putCode);
});

it('checks the token scopes and the API before sending', function (): void {
    $mockClient = new MockClient([]);

    $readOnly = Orcid::member(accessToken: 'token', orcid: '0000-0002-1825-0097', scopes: [Scope::ReadLimited])->withMockClient($mockClient);

    expect(fn (): int => $readOnly->researchResources()->create(testResearchResource('scope')))
        ->toThrow(MissingScopeException::class)
        ->and(fn (): ResearchResource => $readOnly->researchResources()->update(1, testResearchResource('scope')))
        ->toThrow(MissingScopeException::class)
        ->and(fn (): mixed => $readOnly->researchResources()->delete(1))
        ->toThrow(MissingScopeException::class)
        ->and(fn (): Response => Orcid::public()->withMockClient($mockClient)->send(new CreateResearchResource('0000-0002-1825-0097', testResearchResource('public'))))
        ->toThrow(LogicException::class, 'requires the Member API')
        ->and(fn (): mixed => Orcid::member(accessToken: 'token')->researchResources())
        ->toThrow(LogicException::class, 'bound to a record');

    $mockClient->assertNothingSent();
});
