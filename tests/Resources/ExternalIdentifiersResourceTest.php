<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifier;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifierInput;
use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Resources\ReadOnlyExternalIdentifiersResource;

/*
 * Recorded against the ORCID sandbox. Every test only touches external identifiers it created
 * (values start with OrcidFixture::TEST_MARKER) and deletes them.
 * Each test's fixtures are a chain: to re-record, delete the whole folder.
 */

it('creates, reads, updates, lists and deletes an external identifier', function (): void {
    $folder = 'external-identifiers/lifecycle';
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

    $externalIdentifiers = $this->getMemberApiClient(Scope::completeAccess())->withMockClient($mockClient)->externalIdentifiers();

    $putCode = $externalIdentifiers->create(new PersonExternalIdentifierInput(
        'Scopus Author ID',
        OrcidFixture::TEST_MARKER.'-lifecycle',
        'https://example.org/orcid-php-sdk/lifecycle',
    ));

    expect($putCode)->toBeGreaterThan(0);

    $created = $externalIdentifiers->get($putCode);

    expect($created->putCode)->toBe($putCode)
        ->and($created->externalId->type)->toBe('Scopus Author ID')
        ->and($created->externalId->value)->toBe('orcid-php-sdk-lifecycle')
        ->and($created->externalId->url)->toBe('https://example.org/orcid-php-sdk/lifecycle')
        ->and($created->externalId->relationship)->toBe(ExternalIdRelationship::Self)
        ->and($created->visibility)->toBe(Visibility::Public)
        ->and($created->displayIndex)->toBe(1)
        ->and($this->isOurs($created->source))->toBeTrue();

    $record = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId());
    $public = $record->externalIdentifiers()->get($putCode);

    expect($record->externalIdentifiers())->toBeInstanceOf(ReadOnlyExternalIdentifiersResource::class)
        ->and(method_exists($record->externalIdentifiers(), 'create'))->toBeFalse()
        ->and($public->externalId->value)->toBe('orcid-php-sdk-lifecycle');

    $updated = $externalIdentifiers->update($putCode, new PersonExternalIdentifierInput(
        'ResearcherID',
        OrcidFixture::TEST_MARKER.'-lifecycle-updated',
        'https://example.org/orcid-php-sdk/updated',
    ));

    expect($updated->putCode)->toBe($putCode)
        ->and($updated->externalId->type)->toBe('ResearcherID')
        ->and($updated->externalId->value)->toBe('orcid-php-sdk-lifecycle-updated');

    try {
        $list = $externalIdentifiers->list();
        $publicList = $record->externalIdentifiers()->list();
    } finally {
        $externalIdentifiers->delete($putCode);
    }

    $ours = array_values(array_filter($list, fn (PersonExternalIdentifier $id): bool => $id->putCode === $putCode));

    expect($ours)->toHaveCount(1)
        ->and($ours[0]->externalId->value)->toBe('orcid-php-sdk-lifecycle-updated')
        ->and(array_map(fn (PersonExternalIdentifier $id): int => $id->putCode, $publicList))->toContain($putCode)
        ->and(fn (): PersonExternalIdentifier => $externalIdentifiers->get($putCode))->toThrow(OrcidNotFoundException::class);
});

it('refuses a type and value this client already added, whatever the URL', function (): void {
    $folder = 'external-identifiers/duplicate';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/create-again"),
        new OrcidFixture("{$folder}/delete"),
    ]);

    $externalIdentifiers = $this->getMemberApiClient()->withMockClient($mockClient)->externalIdentifiers();
    $putCode = $externalIdentifiers->create(new PersonExternalIdentifierInput('Scopus Author ID', OrcidFixture::TEST_MARKER.'-duplicate', 'https://example.org/a'));

    try {
        $externalIdentifiers->create(new PersonExternalIdentifierInput('Scopus Author ID', OrcidFixture::TEST_MARKER.'-duplicate', 'https://example.org/b'));
        $conflict = null;
    } catch (OrcidConflictException $exception) {
        $conflict = $exception;
    } finally {
        $externalIdentifiers->delete($putCode);
    }

    expect($conflict?->errorCode)->toBe(OrcidConflictException::DUPLICATE_CONTENT)
        ->and($conflict?->isDuplicate())->toBeTrue();
});

it('needs the /person/update scope to write external identifiers', function (string $method, array $arguments): void {
    $externalIdentifiers = $this->getMemberApiClient([Scope::ReadLimited, Scope::ActivitiesUpdate])->externalIdentifiers();

    $externalIdentifiers->{$method}(...$arguments);
})->with([
    'create' => ['create', [new PersonExternalIdentifierInput('Scopus Author ID', '1', 'https://example.org')]],
    'update' => ['update', [1, new PersonExternalIdentifierInput('Scopus Author ID', '1', 'https://example.org')]],
    'delete' => ['delete', [1]],
])->throws(MissingScopeException::class, '/person/update');
