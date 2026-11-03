<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Search;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * One record found by an expanded search: its iD, and the public names, emails and institutions ORCID indexed. A
 * field the researcher keeps private is null or empty.
 */
final readonly class ExpandedSearchResult
{
    /**
     * @param  list<string>  $otherNames
     * @param  list<string>  $emails  Public emails only
     * @param  list<string>  $institutionNames  The organizations of the record's affiliations, current and past
     */
    public function __construct(
        public string $orcid,
        public ?string $givenNames = null,
        /** As PersonName::$familyName (ORCID's key here is `family-names`) */
        public ?string $familyName = null,
        public ?string $creditName = null,
        public array $otherNames = [],
        public array $emails = [],
        public array $institutionNames = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $orcid = Json::string($data, 'orcid-id');

        // a result is about a record: without its iD there is nothing to point to
        if ($orcid === null) {
            return null;
        }

        return new self(
            orcid: $orcid,
            givenNames: Json::string($data, 'given-names'),
            familyName: Json::string($data, 'family-names'),
            creditName: Json::string($data, 'credit-name'),
            otherNames: self::strings($data, 'other-name'),
            emails: self::strings($data, 'email'),
            institutionNames: self::strings($data, 'institution-name'),
        );
    }

    /**
     * The name to show: the credit name, else the given and family names, as PersonName::displayName()
     */
    public function displayName(): ?string
    {
        if ($this->creditName !== null) {
            return $this->creditName;
        }

        $name = trim(($this->givenNames ?? '').' '.($this->familyName ?? ''));

        return $name === '' ? null : $name;
    }

    /**
     * A list of plain strings at `$key`, without blank ones (empty when missing or null)
     *
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function strings(array $data, string $key): array
    {
        $values = is_array($data[$key] ?? null) ? $data[$key] : [];

        return array_values(array_filter($values, static fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
    }
}
