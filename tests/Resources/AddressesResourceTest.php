<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Person\Address;
use VincentAuger\OrcidSdk\Data\Person\AddressInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Resources\ReadOnlyAddressesResource;

/*
 * Recorded against the ORCID sandbox. Every test only touches addresses it created and deletes them. An address has
 * only a country, nothing to mark: test addresses use Antarctica (AQ) and Bouvet Island (BV), which the researcher
 * doesn't list. Each test's fixtures are a chain: to re-record, delete the whole folder.
 */

it('creates, reads, updates, lists and deletes an address', function (): void {
    $folder = 'addresses/lifecycle';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/get"),
        new OrcidFixture("{$folder}/get-public"),
        new OrcidFixture("{$folder}/update"),
        new OrcidFixture("{$folder}/list"),
        new OrcidFixture("{$folder}/list-public"),
        new OrcidFixture("{$folder}/delete"),
        new OrcidFixture("{$folder}/get-deleted"),
    ]);

    $addresses = $this->getMemberApiClient(Scope::completeAccess())->withMockClient($mockClient)->addresses();

    $putCode = $addresses->create(new AddressInput('AQ'));

    expect($putCode)->toBeGreaterThan(0);

    $created = $addresses->get($putCode);

    expect($created->putCode)->toBe($putCode)
        ->and($created->country)->toBe('AQ')
        ->and($created->visibility)->toBe(Visibility::Public)
        ->and($created->displayIndex)->toBe(1)
        ->and($this->isOurs($created->source))->toBeTrue();

    $record = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId());
    $public = $record->addresses()->get($putCode);

    expect($record->addresses())->toBeInstanceOf(ReadOnlyAddressesResource::class)
        ->and(method_exists($record->addresses(), 'create'))->toBeFalse()
        ->and($public->country)->toBe('AQ');

    $updated = $addresses->update($putCode, new AddressInput('BV'));

    expect($updated->putCode)->toBe($putCode)
        ->and($updated->country)->toBe('BV');

    try {
        $list = $addresses->list();
        $publicList = $record->addresses()->list();
    } finally {
        $addresses->delete($putCode);
    }

    $ours = array_values(array_filter($list, fn (Address $address): bool => $address->putCode === $putCode));

    expect($ours)->toHaveCount(1)
        ->and($ours[0]->country)->toBe('BV')
        // the researcher's own country, added by hand
        ->and(array_map(fn (Address $address): string => $address->country, $list))->toContain('CA')
        ->and(array_map(fn (Address $address): int => $address->putCode, $publicList))->toContain($putCode)
        ->and(fn (): Address => $addresses->get($putCode))->toThrow(OrcidNotFoundException::class);
});

it('needs the /person/update scope to write addresses', function (string $method, array $arguments): void {
    $addresses = $this->getMemberApiClient([Scope::ReadLimited, Scope::ActivitiesUpdate])->addresses();

    $addresses->{$method}(...$arguments);
})->with([
    'create' => ['create', [new AddressInput('AQ')]],
    'update' => ['update', [1, new AddressInput('AQ')]],
    'delete' => ['delete', [1]],
])->throws(MissingScopeException::class, '/person/update');
