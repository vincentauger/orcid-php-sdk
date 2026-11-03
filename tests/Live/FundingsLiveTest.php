<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Common\Amount;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Fundings\Funding;
use VincentAuger\OrcidSdk\Data\Fundings\FundingSummary;
use VincentAuger\OrcidSdk\Enums\FundingType;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;

/*
 * Talks to the ORCID sandbox without fixtures (composer test:live): checks that ORCID still behaves as recorded.
 * Skipped without a .env file.
 */

it('creates, reads, updates, lists and deletes a funding on the sandbox', function (): void {
    $this->requiresSandbox();

    $fundings = $this->getMemberApiClient()->fundings();
    $putCode = $fundings->create(testFunding(
        'live lifecycle',
        amount: new Amount('125000.50', 'CAD'),
        startDate: FuzzyDate::fromString('2021-01'),
        endDate: FuzzyDate::fromString('2024'),
    ));

    try {
        $created = $fundings->get($putCode);
        $updated = $fundings->update($putCode, testFunding('live lifecycle', type: FundingType::Award));
        $list = $fundings->list();

        try {
            $fundings->create(testFunding('live lifecycle'));
            $duplicate = null;
        } catch (OrcidConflictException $exception) {
            $duplicate = $exception;
        }
    } finally {
        $fundings->delete($putCode);
    }

    $ours = array_values(array_filter($list->summaries(), fn (FundingSummary $summary): bool => $summary->putCode === $putCode));

    expect($created->title?->title)->toBe('orcid-php-sdk live lifecycle')
        ->and($created->amount?->value)->toBe('125000.5')
        ->and($this->isOurs($created->source))->toBeTrue()
        ->and($updated->type)->toBe(FundingType::Award)
        ->and($updated->amount)->toBeNull()
        ->and($ours)->toHaveCount(1)
        ->and($ours[0]->type)->toBe(FundingType::Award)
        ->and($duplicate?->existingPutCode())->toBe($putCode)
        ->and(fn (): Funding => $fundings->get($putCode))->toThrow(OrcidNotFoundException::class);
})->group('live');
