<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Works\Work;
use VincentAuger\OrcidSdk\Data\Works\WorkGroup;
use VincentAuger\OrcidSdk\Data\Works\WorkSummary;
use VincentAuger\OrcidSdk\Enums\WorkType;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;

/*
 * Talks to the ORCID sandbox without fixtures (composer test:live): checks that ORCID still behaves as recorded.
 * Skipped without a .env file.
 */

it('creates, reads, updates and deletes a work on the sandbox', function (): void {
    $this->requiresSandbox();

    $works = $this->getMemberApiClient()->works();
    $putCode = $works->create(testWork('live lifecycle'));

    try {
        $created = $works->get($putCode);
        $updated = $works->update($putCode, testWork('live lifecycle', type: WorkType::Preprint));

        try {
            $works->create(testWork('live lifecycle'));
            $duplicate = null;
        } catch (OrcidConflictException $exception) {
            $duplicate = $exception;
        }
    } finally {
        $works->delete($putCode);
    }

    expect($created->title?->title)->toBe('orcid-php-sdk live lifecycle')
        ->and($this->isOurs($created->source))->toBeTrue()
        ->and($updated->type)->toBe(WorkType::Preprint)
        ->and($duplicate?->existingPutCode())->toBe($putCode)
        ->and(fn (): Work => $works->get($putCode))->toThrow(OrcidNotFoundException::class);
})->group('live');

it('groups a work with the hand-added work sharing its DOI on the sandbox', function (): void {
    $this->requiresSandbox();

    $works = $this->getMemberApiClient()->works();
    $putCode = $works->create(testWork('live list', externalIds: [new ExternalId('doi', strtoupper(HAND_ADDED_DOI))]));

    try {
        $list = $works->list();
    } finally {
        $works->delete($putCode);
    }

    $group = array_values(array_filter($list->groups, fn (WorkGroup $group): bool => in_array($putCode, array_map(
        fn (WorkSummary $summary): int => $summary->putCode,
        $group->summaries,
    ), true)))[0];

    expect($group->summaries)->toHaveCount(2)
        ->and($group->externalIds[0]->normalizedValue)->toBe(HAND_ADDED_DOI)
        ->and($group->preferred()->putCode)->toBe($putCode);
})->group('live');
