<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Person\ResearcherUrl;
use VincentAuger\OrcidSdk\Data\Person\ResearcherUrlInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Resources\ReadOnlyResearcherUrlsResource;

/*
 * Recorded against the ORCID sandbox. Every test only touches websites it created
 * (names start with OrcidFixture::TEST_MARKER) and deletes them.
 * Each test's fixtures are a chain: to re-record, delete the whole folder.
 */

it('creates, reads, updates, lists and deletes a website', function (): void {
    $folder = 'researcher-urls/lifecycle';
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

    $researcherUrls = $this->getMemberApiClient(Scope::completeAccess())->withMockClient($mockClient)->researcherUrls();

    $putCode = $researcherUrls->create(new ResearcherUrlInput('https://example.org/orcid-php-sdk/lifecycle', OrcidFixture::TEST_MARKER.' lifecycle'));

    expect($putCode)->toBeGreaterThan(0);

    $created = $researcherUrls->get($putCode);

    expect($created->putCode)->toBe($putCode)
        ->and($created->url)->toBe('https://example.org/orcid-php-sdk/lifecycle')
        ->and($created->name)->toBe('orcid-php-sdk lifecycle')
        ->and($created->visibility)->toBe(Visibility::Public)
        ->and($created->displayIndex)->toBe(1)
        ->and($this->isOurs($created->source))->toBeTrue();

    $record = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId());
    $public = $record->researcherUrls()->get($putCode);

    expect($record->researcherUrls())->toBeInstanceOf(ReadOnlyResearcherUrlsResource::class)
        ->and(method_exists($record->researcherUrls(), 'create'))->toBeFalse()
        ->and($public->url)->toBe('https://example.org/orcid-php-sdk/lifecycle');

    $updated = $researcherUrls->update($putCode, new ResearcherUrlInput('https://example.org/orcid-php-sdk/updated', OrcidFixture::TEST_MARKER.' lifecycle updated'));

    expect($updated->putCode)->toBe($putCode)
        ->and($updated->url)->toBe('https://example.org/orcid-php-sdk/updated')
        ->and($updated->name)->toBe('orcid-php-sdk lifecycle updated');

    try {
        $list = $researcherUrls->list();
        $publicList = $record->researcherUrls()->list();
    } finally {
        $researcherUrls->delete($putCode);
    }

    // the fixture keeps the test website and the one the researcher added by hand
    $urls = array_map(fn (ResearcherUrl $url): string => $url->url, $list);

    expect($urls)->toContain('https://example.org/orcid-php-sdk/updated')
        ->and($urls)->toContain('https://dfo-mpo.gc.ca')
        ->and(array_map(fn (ResearcherUrl $url): int => $url->putCode, $publicList))->toContain($putCode)
        ->and(fn (): ResearcherUrl => $researcherUrls->get($putCode))->toThrow(OrcidNotFoundException::class);
});

it('refuses a URL this client already added, whatever its name', function (): void {
    $folder = 'researcher-urls/duplicate';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/create-again"),
        new OrcidFixture("{$folder}/delete"),
    ]);

    $researcherUrls = $this->getMemberApiClient()->withMockClient($mockClient)->researcherUrls();
    $putCode = $researcherUrls->create(new ResearcherUrlInput('https://example.org/orcid-php-sdk/duplicate', OrcidFixture::TEST_MARKER.' duplicate'));

    try {
        $researcherUrls->create(new ResearcherUrlInput('https://example.org/orcid-php-sdk/duplicate', OrcidFixture::TEST_MARKER.' duplicate again'));
        $conflict = null;
    } catch (OrcidConflictException $exception) {
        $conflict = $exception;
    } finally {
        $researcherUrls->delete($putCode);
    }

    expect($conflict?->errorCode)->toBe(OrcidConflictException::DUPLICATE_CONTENT)
        ->and($conflict?->isDuplicate())->toBeTrue();
});

it('needs the /person/update scope to write websites', function (string $method, array $arguments): void {
    $researcherUrls = $this->getMemberApiClient([Scope::ReadLimited, Scope::ActivitiesUpdate])->researcherUrls();

    $researcherUrls->{$method}(...$arguments);
})->with([
    'create' => ['create', [new ResearcherUrlInput('https://example.org')]],
    'update' => ['update', [1, new ResearcherUrlInput('https://example.org')]],
    'delete' => ['delete', [1]],
])->throws(MissingScopeException::class, '/person/update');
