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
     * Read the {"organization": [...]} wrapper used by ORCID (research resource hosts)
     *
     * @param  array<array-key, mixed>|null  $data
     * @return list<self>
     */
    public static function listFromArray(?array $data): array
    {
        if ($data === null) {
            return [];
        }

        return array_values(array_filter(array_map(self::fromArray(...), Json::list($data, 'organization'))));
    }

    /**
     * @param  list<self>  $organizations
     * @return array{organization: list<array<string, mixed>>}
     */
    public static function listToArray(array $organizations): array
    {
        return ['organization' => array_map(static fn (self $organization): array => $organization->toArray(), $organizations)];
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
