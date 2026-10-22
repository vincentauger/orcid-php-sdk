<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * The person section of a record (GET /{orcid}/person). Only data visible to
 * the token is returned: public data, plus limited data with /read-limited.
 */
final readonly class Person
{
    /**
     * @param  list<string>  $otherNames
     * @param  list<ResearcherUrl>  $researcherUrls
     * @param  list<Email>  $emails
     * @param  list<string>  $countries  ISO 3166-1 alpha-2 codes
     * @param  list<string>  $keywords
     * @param  list<ExternalId>  $externalIdentifiers  Identifiers in other systems (Scopus Author ID, ResearcherID, ...)
     */
    public function __construct(
        public ?PersonName $name = null,
        public array $otherNames = [],
        public ?string $biography = null,
        public array $researcherUrls = [],
        public array $emails = [],
        public array $countries = [],
        public array $keywords = [],
        public array $externalIdentifiers = [],
        public ?\DateTimeImmutable $lastModifiedAt = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: PersonName::fromArray(Json::object($data, 'name')),
            otherNames: self::strings($data, 'other-names', 'other-name', 'content'),
            biography: Json::string(Json::object($data, 'biography') ?? [], 'content'),
            researcherUrls: array_values(array_filter(array_map(
                ResearcherUrl::fromArray(...),
                Json::list(Json::object($data, 'researcher-urls') ?? [], 'researcher-url'),
            ))),
            emails: array_values(array_filter(array_map(
                Email::fromArray(...),
                Json::list(Json::object($data, 'emails') ?? [], 'email'),
            ))),
            countries: self::strings($data, 'addresses', 'address', 'country'),
            keywords: self::strings($data, 'keywords', 'keyword', 'content'),
            externalIdentifiers: array_values(array_filter(array_map(
                ExternalId::fromArray(...),
                Json::list(Json::object($data, 'external-identifiers') ?? [], 'external-identifier'),
            ))),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
        );
    }

    public function primaryEmail(): ?Email
    {
        foreach ($this->emails as $email) {
            if ($email->primary) {
                return $email;
            }
        }

        return null;
    }

    /**
     * A list of strings from {"<section>": {"<item>": [{"<field>": ...}]}}
     *
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function strings(array $data, string $section, string $item, string $field): array
    {
        $values = array_map(
            static fn (array $entry): ?string => Json::string($entry, $field),
            Json::list(Json::object($data, $section) ?? [], $item),
        );

        return array_values(array_filter($values, is_string(...)));
    }
}
