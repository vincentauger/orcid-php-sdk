<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Response;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Common\Amount;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\TranslatedTitle;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Fundings\Funding;
use VincentAuger\OrcidSdk\Data\Fundings\FundingContributor;
use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Enums\FundingContributorRole;
use VincentAuger\OrcidSdk\Enums\FundingType;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Fundings\CreateFunding;

/*
 * Recorded against the ORCID sandbox. Every test only touches fundings it created
 * (titles start with OrcidFixture::TEST_MARKER) and deletes them.
 * Each test's fixtures are a chain: to re-record, delete the whole folder.
 */

it('creates, reads, updates, lists and deletes a funding', function (): void {
    $folder = 'fundings/lifecycle';
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
    $fundings = $this->getMemberApiClient(Scope::completeAccess())->withMockClient($mockClient)->fundings();

    $putCode = $fundings->create(testFunding(
        'lifecycle',
        title: new Title(OrcidFixture::TEST_MARKER.' lifecycle', translatedTitle: new TranslatedTitle('Cycle de vie', 'fr')),
        externalIds: [
            ExternalId::grantNumber(OrcidFixture::TEST_MARKER.'-lifecycle', url: 'https://example.org/orcid-php-sdk/grants/lifecycle'),
            ExternalId::grantNumber(OrcidFixture::TEST_MARKER.'-lifecycle-programme', ExternalIdRelationship::PartOf),
        ],
        organizationDefinedType: 'Discovery grant',
        shortDescription: 'A funding created by the SDK tests',
        amount: new Amount('125000.50', 'CAD'),
        url: new Url('https://example.org/orcid-php-sdk/lifecycle'),
        startDate: FuzzyDate::fromString('2021-01-05'),
        endDate: FuzzyDate::fromString('2024-12'),
        contributors: [
            new FundingContributor('Test Lead', $this->orcidId(), FundingContributorRole::Lead),
            new FundingContributor('Co Lead', role: FundingContributorRole::CoLead),
            new FundingContributor('Funding Acquisition', role: FundingContributorRole::FundingAcquisition),
        ],
    ));

    expect($putCode)->toBeGreaterThan(0);

    $created = $fundings->get($putCode);

    expect($created->putCode)->toBe($putCode)
        ->and($created->title?->title)->toBe('orcid-php-sdk lifecycle')
        ->and($created->title?->translatedTitle?->title)->toBe('Cycle de vie')
        ->and($created->type)->toBe(FundingType::Grant)
        ->and($created->rawType)->toBe('grant')
        ->and($created->organizationDefinedType)->toBe('Discovery grant')
        ->and($created->shortDescription)->toBe('A funding created by the SDK tests')
        // ORCID normalizes the value
        ->and($created->amount?->value)->toBe('125000.5')
        ->and($created->amount?->currency)->toBe('CAD')
        ->and((string) $created->url)->toBe('https://example.org/orcid-php-sdk/lifecycle')
        ->and((string) $created->startDate)->toBe('2021-01-05')
        ->and((string) $created->endDate)->toBe('2024-12')
        ->and($created->externalIds)->toHaveCount(2)
        ->and($created->externalIds[0]->value)->toBe('orcid-php-sdk-lifecycle')
        ->and($created->externalIds[0]->url)->toBe('https://example.org/orcid-php-sdk/grants/lifecycle')
        ->and($created->externalIds[1]->relationship)->toBe(ExternalIdRelationship::PartOf)
        ->and($created->contributors)->toHaveCount(3)
        // the placeholder once replayed, the sandbox record while recording
        ->and($created->contributors[0]->orcid)->toBeIn([OrcidFixture::PLACEHOLDER_ID, $this->orcidId()])
        ->and($created->contributors[0]->role)->toBe(FundingContributorRole::Lead)
        ->and($created->contributors[1]->orcid)->toBeNull()
        ->and($created->contributors[1]->creditName)->toBe('Co Lead')
        ->and($created->contributors[1]->role)->toBe(FundingContributorRole::CoLead)
        ->and($created->contributors[2]->role)->toBe(FundingContributorRole::FundingAcquisition)
        ->and($created->organization?->name)->toBe('Fisheries and Oceans Canada')
        ->and($created->organization?->disambiguatedOrganization?->identifier)->toBe('https://ror.org/02qa1x782')
        ->and($this->isOurs($created->source))->toBeTrue()
        ->and($created->createdAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($created->visibility)->toBe(Visibility::Public);

    // every connector reads public fundings through record($id)
    $public = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->fundings()->get($putCode);

    expect($public->title?->title)->toBe('orcid-php-sdk lifecycle')
        ->and($this->isOurs($public->source))->toBeTrue();

    // PUT replaces the whole item: the fields left out are cleared
    $updated = $fundings->update($putCode, testFunding('lifecycle', type: FundingType::Award));

    expect($updated->putCode)->toBe($putCode)
        ->and($updated->type)->toBe(FundingType::Award)
        ->and($updated->title?->translatedTitle)->toBeNull()
        ->and($updated->amount)->toBeNull()
        ->and($updated->organizationDefinedType)->toBeNull()
        ->and($updated->startDate)->toBeNull()
        ->and($updated->contributors)->toBe([])
        ->and($updated->externalIds)->toHaveCount(1);

    // read before the cleanup, asserted after it, so a failed assertion never leaves the funding behind
    try {
        $list = $fundings->list();
        $publicList = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->fundings()->list();
    } finally {
        $fundings->delete($putCode);
    }

    // the fixture only keeps the group holding the test funding
    expect($list->groups)->toHaveCount(1)
        ->and($list->groups[0]->externalIds[0]->value)->toBe('orcid-php-sdk-lifecycle')
        ->and($list->groups[0]->summaries)->toHaveCount(1)
        ->and($list->preferred())->toBe($list->summaries())
        ->and($list->summaries()[0]->putCode)->toBe($putCode)
        ->and($list->summaries()[0]->title?->title)->toBe('orcid-php-sdk lifecycle')
        ->and($list->summaries()[0]->type)->toBe(FundingType::Award)
        ->and($list->summaries()[0]->organization?->name)->toBe('Fisheries and Oceans Canada')
        ->and($list->summaries()[0]->displayIndex)->toBe(1)
        ->and($this->isOurs($list->summaries()[0]->source))->toBeTrue()
        ->and($publicList->summaries()[0]->putCode)->toBe($putCode)
        ->and(fn (): Funding => $fundings->get($putCode))->toThrow(OrcidNotFoundException::class);
});

it('refuses a second funding with the same self identifier', function (): void {
    $folder = 'fundings/duplicate';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/create-again"),
        new OrcidFixture("{$folder}/delete"),
    ]);

    $fundings = $this->getMemberApiClient()->withMockClient($mockClient)->fundings();

    $putCode = $fundings->create(testFunding('duplicate'));

    // caught before the cleanup, so a failed assertion never leaves the funding behind
    try {
        $fundings->create(testFunding('duplicate', type: FundingType::Contract));
        $conflict = null;
    } catch (OrcidConflictException $exception) {
        $conflict = $exception;
    } finally {
        $fundings->delete($putCode);
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

    expect(fn (): int => $readOnly->fundings()->create(testFunding('scope')))
        ->toThrow(MissingScopeException::class)
        ->and(fn (): Funding => $readOnly->fundings()->update(1, testFunding('scope')))
        ->toThrow(MissingScopeException::class)
        ->and(fn (): mixed => $readOnly->fundings()->delete(1))
        ->toThrow(MissingScopeException::class)
        ->and(fn (): Response => Orcid::public()->withMockClient($mockClient)->send(new CreateFunding('0000-0002-1825-0097', testFunding('public'))))
        ->toThrow(LogicException::class, 'requires the Member API')
        ->and(fn (): mixed => Orcid::member(accessToken: 'token')->fundings())
        ->toThrow(LogicException::class, 'bound to a record');

    $mockClient->assertNothingSent();
});
