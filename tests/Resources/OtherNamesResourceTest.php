<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Person\OtherName;
use VincentAuger\OrcidSdk\Data\Person\OtherNameInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Resources\ReadOnlyOtherNamesResource;

/*
 * Recorded against the ORCID sandbox. Every test only touches other names it created
 * (contents start with OrcidFixture::TEST_MARKER) and deletes them.
 * Each test's fixtures are a chain: to re-record, delete the whole folder.
 */

it('creates, reads, updates, lists and deletes an other name', function (): void {
    $folder = 'other-names/lifecycle';
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
    $otherNames = $this->getMemberApiClient(Scope::completeAccess())->withMockClient($mockClient)->otherNames();

    $putCode = $otherNames->create(new OtherNameInput(OrcidFixture::TEST_MARKER.' lifecycle'));

    expect($putCode)->toBeGreaterThan(0);

    $created = $otherNames->get($putCode);

    expect($created->putCode)->toBe($putCode)
        ->and($created->content)->toBe('orcid-php-sdk lifecycle')
        ->and($created->visibility)->toBe(Visibility::Public)
        ->and($created->displayIndex)->toBe(1)
        ->and($this->isOurs($created->source))->toBeTrue()
        ->and($created->createdAt)->toBeInstanceOf(DateTimeImmutable::class);

    // every connector reads public other names through record($id)
    $record = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId());
    $public = $record->otherNames()->get($putCode);

    expect($record->otherNames())->toBeInstanceOf(ReadOnlyOtherNamesResource::class)
        ->and(method_exists($record->otherNames(), 'create'))->toBeFalse()
        ->and($public->content)->toBe('orcid-php-sdk lifecycle');

    $updated = $otherNames->update($putCode, new OtherNameInput(OrcidFixture::TEST_MARKER.' lifecycle updated'));

    expect($updated->putCode)->toBe($putCode)
        ->and($updated->content)->toBe('orcid-php-sdk lifecycle updated');

    // read before the cleanup, asserted after it, so a failed assertion never leaves the other name behind
    try {
        $list = $otherNames->list();
        $publicList = $record->otherNames()->list();
    } finally {
        $otherNames->delete($putCode);
    }

    // the fixture keeps the test name and the one the researcher added by hand (issue #3)
    $contents = array_map(fn (OtherName $name): string => $name->content, $list);

    expect($contents)->toContain('orcid-php-sdk lifecycle updated')
        ->and($contents)->toContain('JP Auger')
        ->and(array_map(fn (OtherName $name): int => $name->putCode, $publicList))->toContain($putCode)
        ->and(fn (): OtherName => $otherNames->get($putCode))->toThrow(OrcidNotFoundException::class);
});

it('refuses an other name this client already added', function (): void {
    $folder = 'other-names/duplicate';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/create-again"),
        new OrcidFixture("{$folder}/delete"),
    ]);

    $otherNames = $this->getMemberApiClient()->withMockClient($mockClient)->otherNames();
    $input = new OtherNameInput(OrcidFixture::TEST_MARKER.' duplicate');
    $putCode = $otherNames->create($input);

    try {
        $otherNames->create($input);
        $conflict = null;
    } catch (OrcidConflictException $exception) {
        $conflict = $exception;
    } finally {
        $otherNames->delete($putCode);
    }

    expect($conflict?->errorCode)->toBe(OrcidConflictException::DUPLICATE_CONTENT)
        ->and($conflict?->isDuplicate())->toBeTrue()
        ->and($conflict?->existingPutCode())->toBeNull();
});

it('needs the /person/update scope to write other names', function (string $method, array $arguments): void {
    $otherNames = $this->getMemberApiClient([Scope::ReadLimited, Scope::ActivitiesUpdate])->otherNames();

    $otherNames->{$method}(...$arguments);
})->with([
    'create' => ['create', [new OtherNameInput(OrcidFixture::TEST_MARKER.' scope')]],
    'update' => ['update', [1, new OtherNameInput(OrcidFixture::TEST_MARKER.' scope')]],
    'delete' => ['delete', [1]],
])->throws(MissingScopeException::class, '/person/update');
