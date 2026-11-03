<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResource;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceSummary;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;

/*
 * Talks to the ORCID sandbox without fixtures (composer test:live): checks that ORCID still behaves as recorded.
 * Skipped without a .env file.
 */

it('creates, reads, updates, lists and deletes a research resource on the sandbox', function (): void {
    $this->requiresSandbox();

    $researchResources = $this->getMemberApiClient()->researchResources();
    $putCode = $researchResources->create(testResearchResource(
        'live lifecycle',
        startDate: FuzzyDate::fromString('2021-07'),
        endDate: FuzzyDate::fromString('2021-08'),
    ));

    try {
        $created = $researchResources->get($putCode);
        $updated = $researchResources->update($putCode, testResearchResource('live lifecycle', url: new Url('https://example.org/orcid-php-sdk/live')));
        $list = $researchResources->list();

        try {
            $duplicatePutCode = $researchResources->create(testResearchResource('live lifecycle'));
            $duplicate = null;
        } catch (OrcidConflictException $exception) {
            $duplicatePutCode = null;
            $duplicate = $exception;
        }
    } finally {
        $researchResources->delete($putCode);

        // only if ORCID stopped refusing duplicates: never leave a test item behind
        if (isset($duplicatePutCode)) {
            $researchResources->delete($duplicatePutCode);
        }
    }

    $ours = array_values(array_filter($list->summaries(), fn (ResearchResourceSummary $summary): bool => $summary->putCode === $putCode));

    expect($created->proposal?->title?->title)->toBe('orcid-php-sdk live lifecycle')
        ->and((string) $created->proposal?->endDate)->toBe('2021-08')
        ->and($created->items)->toHaveCount(1)
        ->and($this->isOurs($created->source))->toBeTrue()
        ->and((string) $updated->proposal?->url)->toBe('https://example.org/orcid-php-sdk/live')
        ->and($updated->proposal?->startDate)->toBeNull()
        ->and($ours)->toHaveCount(1)
        ->and($ours[0]->proposal?->title?->title)->toBe('orcid-php-sdk live lifecycle')
        ->and($duplicate?->existingPutCode())->toBe($putCode)
        ->and(fn (): ResearchResource => $researchResources->get($putCode))->toThrow(OrcidNotFoundException::class);
})->group('live');
