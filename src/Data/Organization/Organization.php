<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Organization;

use VincentAuger\OrcidSdk\Support\Json;

final readonly class Organization
{
    public function __construct(
        public string $name,
        public ?OrganizationAddress $address = null,
        public ?DisambiguatedOrganization $disambiguatedOrganization = null,
    ) {}

    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        $name = $data === null ? null : Json::string($data, 'name');

        if ($data === null || $name === null) {
            return null;
        }

        return new self(
            name: $name,
            address: OrganizationAddress::fromArray(Json::object($data, 'address')),
            disambiguatedOrganization: DisambiguatedOrganization::fromArray(Json::object($data, 'disambiguated-organization')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::withoutNulls([
            'name' => trim($this->name),
            'address' => $this->address?->toArray(),
            'disambiguated-organization' => $this->disambiguatedOrganization?->toArray(),
        ]);
    }
}
