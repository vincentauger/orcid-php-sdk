<?php

declare(strict_types=1);

use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Person\Keyword;
use VincentAuger\OrcidSdk\Data\Person\KeywordInput;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;

/*
 * Talks to the ORCID sandbox without fixtures (composer test:live): checks that ORCID still behaves as recorded.
 * Skipped without a .env file.
 */

it('creates, reads, updates, lists and deletes a keyword on the sandbox', function (): void {
    $this->requiresSandbox();

    $orcid = $this->getMemberApiClient();
    $keywords = $orcid->keywords();
    $putCode = $keywords->create(new KeywordInput(OrcidFixture::TEST_MARKER.' live lifecycle'));

    try {
        $created = $keywords->get($putCode);
        $updated = $keywords->update($putCode, new KeywordInput(OrcidFixture::TEST_MARKER.' live lifecycle updated'));
        $list = $keywords->list();
        $person = $orcid->person();

        try {
            $keywords->create(new KeywordInput(OrcidFixture::TEST_MARKER.' live lifecycle updated'));
            $duplicate = null;
        } catch (OrcidConflictException $exception) {
            $duplicate = $exception;
        }
    } finally {
        $keywords->delete($putCode);
    }

    $ours = static fn (array $items): array => array_values(array_filter($items, fn (Keyword $keyword): bool => $keyword->putCode === $putCode));

    expect($created->content)->toBe('orcid-php-sdk live lifecycle')
        ->and($this->isOurs($created->source))->toBeTrue()
        ->and($updated->content)->toBe('orcid-php-sdk live lifecycle updated')
        ->and($ours($list))->toHaveCount(1)
        ->and($ours($person->keywords))->toEqual($ours($list))
        ->and($duplicate?->errorCode)->toBe(OrcidConflictException::DUPLICATE_CONTENT)
        ->and(fn (): Keyword => $keywords->get($putCode))->toThrow(OrcidNotFoundException::class);
})->group('live');
