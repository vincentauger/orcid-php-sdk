<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * The person section of a record (GET /{orcid}/person). Only data visible to
 * the token is returned: public data, plus limited data with /read-limited.
 *
 * Each item has its put-code, visibility and source, and is the same class a
 * section's own resource returns (e.g. Keyword, from keywords()->list()). The
 * name and biography have no put-code or source (only the researcher edits
 * them), and ORCID returns emails with a null put-code.
 */
final readonly class Person
{
    /**
     * @param  list<OtherName>  $otherNames
     * @param  list<ResearcherUrl>  $researcherUrls
     * @param  list<Email>  $emails
     * @param  list<Address>  $addresses  The countries the researcher lists
     * @param  list<Keyword>  $keywords
     * @param  list<PersonExternalIdentifier>  $externalIdentifiers  Identifiers in other systems (Scopus Author ID, ResearcherID, ...)
     */
    public function __construct(
        public ?PersonName $name = null,
        public array $otherNames = [],
        public ?Biography $biography = null,
        public array $researcherUrls = [],
        public array $emails = [],
        public array $addresses = [],
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
            otherNames: OtherName::listFromArray(Json::object($data, 'other-names')),
            biography: Biography::fromArray(Json::object($data, 'biography')),
            researcherUrls: ResearcherUrl::listFromArray(Json::object($data, 'researcher-urls')),
            emails: Email::listFromArray(Json::object($data, 'emails')),
            addresses: Address::listFromArray(Json::object($data, 'addresses')),
            keywords: Keyword::listFromArray(Json::object($data, 'keywords')),
            externalIdentifiers: PersonExternalIdentifier::listFromArray(Json::object($data, 'external-identifiers')),
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
}
