<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Person\AddressInput;

it('sends the country, and the put-code on an update', function (): void {
    expect(new AddressInput('CA')->toArray())->toBe(['country' => ['value' => 'CA']])
        ->and(new AddressInput('CA')->toArray(8240))->toBe(['put-code' => 8240, 'country' => ['value' => 'CA']]);
});

it('uppercases the country, which ORCID refuses in lowercase (9047)', function (): void {
    expect(new AddressInput(' ca ')->country)->toBe('CA');
});

it('refuses anything but an ISO 3166-1 alpha-2 code (9047)', function (string $country): void {
    new AddressInput($country);
})->with(['', ' ', 'CAN', 'C', 'C1'])->throws(InvalidArgumentException::class, 'ISO 3166-1 alpha-2');
