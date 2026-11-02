<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Works\Work;
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
