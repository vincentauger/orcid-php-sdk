<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Person\OtherNameInput;

it('sends the content, and the put-code on an update', function (): void {
    expect(new OtherNameInput('Jo Carberry')->toArray())->toBe(['content' => 'Jo Carberry'])
        ->and(new OtherNameInput('Jo Carberry')->toArray(19156))->toBe(['put-code' => 19156, 'content' => 'Jo Carberry']);
});

it('trims the name, which ORCID stores as sent', function (): void {
    expect(new OtherNameInput('  Jo Carberry ')->content)->toBe('Jo Carberry');
});

it('refuses a blank name, which ORCID would store', function (string $content): void {
    new OtherNameInput($content);
})->with(['', '   '])->throws(InvalidArgumentException::class, 'cannot be blank');

it('accepts 255 characters and refuses more (9047)', function (): void {
    expect(new OtherNameInput(str_repeat('é', 255))->content)->toHaveLength(255)
        ->and(fn (): OtherNameInput => new OtherNameInput(str_repeat('é', 256)))
        ->toThrow(InvalidArgumentException::class, 'longer than 255 characters');
});
