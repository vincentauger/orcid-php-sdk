<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Person\Keyword;
use VincentAuger\OrcidSdk\Data\Person\KeywordInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Resources\ReadOnlyKeywordsResource;

/*
 * Recorded against the ORCID sandbox. Every test only touches keywords it created
 * (contents start with OrcidFixture::TEST_MARKER) and deletes them.
 * Each test's fixtures are a chain: to re-record, delete the whole folder.
 */

it('creates, reads, updates, lists and deletes a keyword', function (): void {
    $folder = 'keywords/lifecycle';
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
    $keywords = $this->getMemberApiClient(Scope::completeAccess())->withMockClient($mockClient)->keywords();

    $putCode = $keywords->create(new KeywordInput(OrcidFixture::TEST_MARKER.' lifecycle'));

    expect($putCode)->toBeGreaterThan(0);

    $created = $keywords->get($putCode);

    expect($created->putCode)->toBe($putCode)
        ->and($created->content)->toBe('orcid-php-sdk lifecycle')
        ->and($created->visibility)->toBe(Visibility::Public)
        ->and($created->displayIndex)->toBe(1)
        ->and($this->isOurs($created->source))->toBeTrue()
        ->and($created->createdAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($created->lastModifiedAt)->toBeInstanceOf(DateTimeImmutable::class);

    // every connector reads public keywords through record($id)
    $record = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId());
    $public = $record->keywords()->get($putCode);

    expect($record->keywords())->toBeInstanceOf(ReadOnlyKeywordsResource::class)
        ->and(method_exists($record->keywords(), 'create'))->toBeFalse()
        ->and($public->content)->toBe('orcid-php-sdk lifecycle')
        ->and($this->isOurs($public->source))->toBeTrue();

    $updated = $keywords->update($putCode, new KeywordInput(OrcidFixture::TEST_MARKER.' lifecycle updated'));

    expect($updated->putCode)->toBe($putCode)
        ->and($updated->content)->toBe('orcid-php-sdk lifecycle updated');

    // read before the cleanup, asserted after it, so a failed assertion never leaves the keyword behind
    try {
        $list = $keywords->list();
        $publicList = $record->keywords()->list();
    } finally {
        $keywords->delete($putCode);
    }

    // the fixture only keeps the test keyword; while recording, the researcher's own are listed too
    $ours = array_values(array_filter($list, fn (Keyword $keyword): bool => $keyword->putCode === $putCode));

    expect($ours)->toHaveCount(1)
        ->and($ours[0]->content)->toBe('orcid-php-sdk lifecycle updated')
        ->and($this->isOurs($ours[0]->source))->toBeTrue()
        ->and(array_map(fn (Keyword $keyword): int => $keyword->putCode, $publicList))->toContain($putCode)
        ->and(fn (): Keyword => $keywords->get($putCode))->toThrow(OrcidNotFoundException::class);
});

it('refuses a keyword this client already added', function (): void {
    $folder = 'keywords/duplicate';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/create-again"),
        new OrcidFixture("{$folder}/delete"),
    ]);

    $keywords = $this->getMemberApiClient()->withMockClient($mockClient)->keywords();
    $input = new KeywordInput(OrcidFixture::TEST_MARKER.' duplicate');
    $putCode = $keywords->create($input);

    try {
        $keywords->create($input);
        $conflict = null;
    } catch (OrcidConflictException $exception) {
        $conflict = $exception;
    } finally {
        $keywords->delete($putCode);
    }

    // unlike a duplicate activity (9021), ORCID does not name the existing keyword
    expect($conflict?->errorCode)->toBe(OrcidConflictException::DUPLICATE_CONTENT)
        ->and($conflict?->isDuplicate())->toBeTrue()
        ->and($conflict?->existingPutCode())->toBeNull();
});

it('needs the /person/update scope to write keywords', function (string $method, array $arguments): void {
    $keywords = $this->getMemberApiClient([Scope::ReadLimited, Scope::ActivitiesUpdate])->keywords();

    $keywords->{$method}(...$arguments);
})->with([
    'create' => ['create', [new KeywordInput(OrcidFixture::TEST_MARKER.' scope')]],
    'update' => ['update', [1, new KeywordInput(OrcidFixture::TEST_MARKER.' scope')]],
    'delete' => ['delete', [1]],
])->throws(MissingScopeException::class, '/person/update');
