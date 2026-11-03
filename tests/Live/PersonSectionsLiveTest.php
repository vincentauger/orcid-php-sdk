<?php

declare(strict_types=1);

use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Person\AddressInput;
use VincentAuger\OrcidSdk\Data\Person\OtherNameInput;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifierInput;
use VincentAuger\OrcidSdk\Data\Person\ResearcherUrlInput;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Orcid;

/*
 * Talks to the ORCID sandbox without fixtures (composer test:live): checks that ORCID still behaves as recorded.
 * Skipped without a .env file.
 */

it('creates, reads, updates, lists and deletes a person item on the sandbox', function (
    string $section,
    object $input,
    object $updatedInput,
    Closure $read,
    bool $refusesDuplicates,
): void {
    $this->requiresSandbox();

    /** @var Orcid $orcid */
    $orcid = $this->getMemberApiClient();
    $resource = $orcid->{$section}();
    $putCode = $resource->create($input);

    try {
        $created = $resource->get($putCode);
        $updated = $resource->update($putCode, $updatedInput);
        $list = $resource->list();
        $person = $orcid->person();

        try {
            // addresses accept the same country twice: remove the second one
            $resource->delete($resource->create($updatedInput));
            $duplicate = null;
        } catch (OrcidConflictException $exception) {
            $duplicate = $exception;
        }
    } finally {
        $resource->delete($putCode);
    }

    $ours = static fn (array $items): array => array_values(array_filter($items, fn (object $item): bool => $item->putCode === $putCode));

    expect($read($created))->toBe($read($input))
        ->and($this->isOurs($created->source))->toBeTrue()
        ->and($read($updated))->toBe($read($updatedInput))
        ->and($ours($list))->toHaveCount(1)
        ->and($ours($person->{$section}))->toEqual($ours($list))
        ->and($duplicate?->errorCode)->toBe($refusesDuplicates ? OrcidConflictException::DUPLICATE_CONTENT : null)
        ->and(fn (): object => $resource->get($putCode))->toThrow(OrcidNotFoundException::class);
})->with([
    'other names' => [
        'otherNames',
        new OtherNameInput(OrcidFixture::TEST_MARKER.' live lifecycle'),
        new OtherNameInput(OrcidFixture::TEST_MARKER.' live lifecycle updated'),
        fn (object $item): string => $item->content,
        true,
    ],
    'researcher URLs' => [
        'researcherUrls',
        new ResearcherUrlInput('https://example.org/orcid-php-sdk/live', OrcidFixture::TEST_MARKER.' live lifecycle'),
        new ResearcherUrlInput('https://example.org/orcid-php-sdk/live-updated', OrcidFixture::TEST_MARKER.' live lifecycle updated'),
        fn (object $item): string => $item->url,
        true,
    ],
    'external identifiers' => [
        'externalIdentifiers',
        new PersonExternalIdentifierInput('Scopus Author ID', OrcidFixture::TEST_MARKER.'-live', 'https://example.org/orcid-php-sdk/live'),
        new PersonExternalIdentifierInput('Scopus Author ID', OrcidFixture::TEST_MARKER.'-live-updated', 'https://example.org/orcid-php-sdk/live'),
        fn (object $item): string => $item instanceof PersonExternalIdentifierInput ? $item->value : $item->externalId->value,
        true,
    ],
    'addresses' => [
        'addresses',
        new AddressInput('AQ'),
        new AddressInput('BV'),
        fn (object $item): string => $item->country,
        false,
    ],
])->group('live');
