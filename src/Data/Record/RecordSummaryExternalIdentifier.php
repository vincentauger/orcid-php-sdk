<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Record;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * A person external identifier as the record summary shows it: shorter than a PersonExternalIdentifier (no
 * relationship, source or visibility)
 */
final readonly class RecordSummaryExternalIdentifier
{
    public function __construct(
        public int $putCode,
        /** Free text, e.g. "Scopus Author ID" */
        public string $type,
        public string $value,
        public ?string $url = null,
        /** Added by a client (true) or by the researcher themselves (false, self-asserted) */
        public ?bool $validated = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $putCode = Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID record summary external identifier has no put-code');
        $type = Json::string($data, 'external-id-type');
        $value = Json::string($data, 'external-id-value');

        // a blank type or value is left out, as in Person
        if ($type === null || $value === null) {
            return null;
        }

        return new self(
            putCode: $putCode,
            type: $type,
            value: $value,
            url: Json::string($data, 'external-id-url'),
            validated: Json::bool($data, 'validated'),
        );
    }

    /**
     * Read the summary's {"external-identifier": [...]} (null when the record has none)
     *
     * @param  array<array-key, mixed>|null  $data
     * @return list<self>
     */
    public static function listFromArray(?array $data): array
    {
        return array_values(array_filter(array_map(self::fromArray(...), Json::list($data ?? [], 'external-identifier'))));
    }
}
