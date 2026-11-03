<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Record;

use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * An affiliation as the record summary shows it: shorter than an Affiliation (no department, address or source).
 * Read it by put-code (affiliations()->get()) for the rest.
 */
final readonly class RecordSummaryAffiliation
{
    public function __construct(
        public int $putCode,
        /** null for a type ORCID added later, whose value is kept in rawType */
        public ?AffiliationType $type,
        public ?string $rawType,
        public ?string $organizationName = null,
        /** The role title */
        public ?string $role = null,
        public ?string $url = null,
        public ?FuzzyDate $startDate = null,
        public ?FuzzyDate $endDate = null,
        /** Added by a client (true) or by the researcher themselves (false, self-asserted) */
        public ?bool $validated = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID record summary affiliation has no put-code'),
            type: Json::enum($data, 'type', AffiliationType::class),
            rawType: Json::string($data, 'type'),
            organizationName: Json::string($data, 'organization-name'),
            role: Json::string($data, 'role'),
            url: Json::string($data, 'url'),
            startDate: FuzzyDate::fromArray(Json::object($data, 'start-date')),
            endDate: FuzzyDate::fromArray(Json::object($data, 'end-date')),
            validated: Json::bool($data, 'validated'),
        );
    }

    /**
     * Read one of the summary's affiliation sections ({"count": 2, "employment": [...]})
     *
     * @param  array<array-key, mixed>|null  $data
     * @return list<self>
     */
    public static function listFromArray(?array $data, string $key): array
    {
        return array_map(self::fromArray(...), Json::list($data ?? [], $key));
    }
}
