<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * A country the researcher lists on their record (ORCID only stores the country of an address)
 */
final readonly class Address
{
    public function __construct(
        /** ISO 3166-1 alpha-2 code */
        public string $country,
        public int $putCode,
        public ?Visibility $visibility = null,
        public ?Source $source = null,
        /** The order the researcher chose among their countries */
        public ?int $displayIndex = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     * @return self|null null without a country
     */
    public static function fromArray(array $data): ?self
    {
        $country = Json::string($data, 'country');

        if ($country === null) {
            return null;
        }

        return new self(
            country: $country,
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID address has no put-code'),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            source: Source::fromArray(Json::object($data, 'source')),
            displayIndex: Json::int($data, 'display-index'),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
        );
    }

    /**
     * Read the {"address": [...]} list (the person section's "addresses")
     *
     * @param  array<array-key, mixed>|null  $data
     * @return list<self>
     */
    public static function listFromArray(?array $data): array
    {
        return array_values(array_filter(array_map(self::fromArray(...), Json::list($data ?? [], 'address'))));
    }
}
