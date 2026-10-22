<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Data\Affiliations\AffiliationInput;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;
use VincentAuger\OrcidSdk\Exceptions\OrcidForbiddenException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Orcid;

/*
 * Recorded against the ORCID sandbox. Every test only touches items it created
 * (department names start with OrcidFixture::TEST_MARKER) and deletes them.
 * Each test's fixtures are a chain: to re-record, delete the whole folder.
 */

function testAffiliation(string $label, ?string $roleTitle = 'Tester', ?FuzzyDate $endDate = null): AffiliationInput
{
    return new AffiliationInput(
        organization: new Organization(
            'Fisheries and Oceans Canada',
            new OrganizationAddress('Ottawa', 'CA', 'ON'),
            DisambiguatedOrganization::ror('https://ror.org/02qa1x782'),
        ),
        departmentName: OrcidFixture::TEST_MARKER.' '.$label,
        roleTitle: $roleTitle,
        startDate: FuzzyDate::fromString('2021-01-05'),
        endDate: $endDate,
        externalIds: [new ExternalId('grant_number', 'ORCID-PHP-SDK-1')],
    );
}

it('creates, reads, updates, lists and deletes an employment', function (): void {
    $folder = 'affiliations/employment-lifecycle';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/get"),
        new OrcidFixture("{$folder}/update"),
        new OrcidFixture("{$folder}/list"),
        new OrcidFixture("{$folder}/delete"),
        new OrcidFixture("{$folder}/get-deleted"),
    ]);

    $affiliations = $this->getMemberApiClient()->withMockClient($mockClient)->record($this->orcidId())->affiliations();

    $putCode = $affiliations->create(AffiliationType::Employment, testAffiliation('lifecycle'));

    expect($putCode)->toBeGreaterThan(0);

    $created = $affiliations->get(AffiliationType::Employment, $putCode);

    expect($created->putCode)->toBe($putCode)
        ->and($created->departmentName)->toBe('orcid-php-sdk lifecycle')
        ->and($created->roleTitle)->toBe('Tester')
        ->and((string) $created->startDate)->toBe('2021-01-05')
        ->and($created->endDate)->toBeNull()
        ->and($created->externalIds)->toHaveCount(1)
        ->and($created->externalIds[0]->value)->toBe('ORCID-PHP-SDK-1')
        ->and($created->organization?->disambiguatedOrganization?->identifier)->toBe('https://ror.org/02qa1x782');

    // PUT replaces the whole item: the role title left out is cleared
    $updated = $affiliations->update(AffiliationType::Employment, $putCode, testAffiliation('lifecycle', roleTitle: null, endDate: FuzzyDate::fromString('2023')));

    expect($updated->roleTitle)->toBeNull()
        ->and((string) $updated->endDate)->toBe('2023');

    $listed = array_map(fn (Affiliation $affiliation): int => $affiliation->putCode, $affiliations->list(AffiliationType::Employment));

    expect($listed)->toContain($putCode);

    $affiliations->delete(AffiliationType::Employment, $putCode);

    expect(fn (): Affiliation => $affiliations->get(AffiliationType::Employment, $putCode))
        ->toThrow(OrcidNotFoundException::class);
});

it('creates, lists and deletes every affiliation type', function (AffiliationType $type): void {
    $folder = 'affiliations/'.$type->value;
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/list"),
        new OrcidFixture("{$folder}/delete"),
    ]);

    $affiliations = $this->getMemberApiClient()->withMockClient($mockClient)->record($this->orcidId())->affiliations();

    $putCode = $affiliations->create($type, testAffiliation($type->value));
    $listed = $affiliations->list($type);
    $affiliations->delete($type, $putCode);

    $ours = array_values(array_filter($listed, fn (Affiliation $affiliation): bool => $affiliation->putCode === $putCode));

    expect($ours)->toHaveCount(1)
        ->and($ours[0]->type)->toBe($type)
        ->and($ours[0]->departmentName)->toBe(OrcidFixture::TEST_MARKER.' '.$type->value);
})->with(AffiliationType::cases());

it('refuses to update an item created by another source', function (): void {
    // put-code 71553 is an employment the sandbox record owner added by hand
    $mockClient = new MockClient([new OrcidFixture('affiliations/update-other-source')]);

    $affiliations = $this->getMemberApiClient()->withMockClient($mockClient)->record($this->orcidId())->affiliations();

    expect(fn (): Affiliation => $affiliations->update(AffiliationType::Employment, 71553, testAffiliation('not ours')))
        ->toThrow(function (OrcidForbiddenException $exception): void {
            expect($exception->isNotSource())->toBeTrue();
        });
});

it('checks the token scopes and the API before sending', function (): void {
    $mockClient = new MockClient([]);

    $readOnly = Orcid::member('token', scopes: [Scope::ReadLimited])->withMockClient($mockClient);

    expect(fn (): int => $readOnly->record('0000-0002-1825-0097')->affiliations()->create(AffiliationType::Employment, testAffiliation('scope')))
        ->toThrow(MissingScopeException::class)
        ->and(fn (): int => Orcid::public()->withMockClient($mockClient)->record('0000-0002-1825-0097')->affiliations()->create(AffiliationType::Employment, testAffiliation('public')))
        ->toThrow(LogicException::class, 'requires the Member API');

    $mockClient->assertNothingSent();
});

it('rejects invalid ORCID iDs', function (): void {
    Orcid::public()->record('0000-0002-1825-0098');
})->throws(InvalidArgumentException::class, 'bad checksum');
