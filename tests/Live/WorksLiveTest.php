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

it('creates and reads works in bulk on the sandbox', function (): void {
    $this->requiresSandbox();

    $works = $this->getMemberApiClient()->works();
    $result = $works->createMany([testWork('live bulk'), testWork('live bulk'), testWork('live bulk second')]);

    try {
        $read = $works->getMany([...array_values($result->created), 999999999]);
    } finally {
        foreach ($result->created as $putCode) {
            $works->delete($putCode);
        }
    }

    expect(array_keys($result->created))->toBe([0, 2])
        ->and($result->failed[1]->existingPutCode())->toBe($result->created[0])
        ->and(array_keys($read->works))->toBe(array_values($result->created))
        ->and($read->failed[999999999]->errorCode)->toBe(9034);
})->group('live');

it('groups a work with the hand-added work sharing its DOI on the sandbox', function (): void {
    $this->requiresSandbox();

    $works = $this->getMemberApiClient()->works();
    $putCode = $works->create(testWork('live list', externalIds: [ExternalId::doi(strtoupper(HAND_ADDED_DOI))]));

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
        // the researcher chose the hand-added work as preferred (works/preferred): a new work doesn't take over
        ->and($group->preferred()->source?->isRecordOwner())->toBeTrue()
        ->and($group->preferred()->putCode)->not->toBe($putCode);
})->group('live');
