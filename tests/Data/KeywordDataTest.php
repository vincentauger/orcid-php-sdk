<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Person\KeywordInput;

it('sends the content, and the put-code on an update', function (): void {
    expect(new KeywordInput('ceramics')->toArray())->toBe(['content' => 'ceramics'])
        ->and(new KeywordInput('ceramics')->toArray(8400))->toBe(['put-code' => 8400, 'content' => 'ceramics']);
});

it('trims the content, which ORCID stores as sent', function (): void {
    expect(new KeywordInput('  ceramics ')->content)->toBe('ceramics');
});

it('refuses a blank keyword (9020)', function (string $content): void {
    new KeywordInput($content);
})->with(['', '   '])->throws(InvalidArgumentException::class, 'non-blank');

it('accepts 255 characters and refuses more (9047)', function (): void {
    expect(new KeywordInput(str_repeat('é', 255))->content)->toHaveLength(255)
        ->and(fn (): KeywordInput => new KeywordInput(str_repeat('é', 256)))
        ->toThrow(InvalidArgumentException::class, 'longer than 255 characters');
});
