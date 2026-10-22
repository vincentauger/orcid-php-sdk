<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Organization;

use VincentAuger\OrcidSdk\Support\Json;

final readonly class OrganizationAddress
{
    /**
     * Fields are nullable so existing items can always be read; AffiliationInput
     * requires a city and a country before sending (ORCID error 9060).
     */
    public function __construct(
        public ?string $city,
        /** ISO 3166-1 alpha-2 country code, e.g. "CA" */
        public ?string $country,
        public ?string $region = null,
    ) {}

    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(
            city: Json::string($data, 'city'),
            country: Json::string($data, 'country'),
            region: Json::string($data, 'region'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        /** @var array<string, string> */
        return Json::withoutNulls([
            'city' => Json::blankToNull($this->city),
            'region' => Json::blankToNull($this->region),
            'country' => $this->country === null ? null : strtoupper(trim($this->country)),
        ]);
    }
}
