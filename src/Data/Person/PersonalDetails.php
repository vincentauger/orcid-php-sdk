<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * The personal details of a record (GET /{orcid}/personal-details): the part of the person section holding the
 * name, other names and biography, as the same classes as Person. Only data visible to the token is returned.
 */
final readonly class PersonalDetails
{
    /**
     * @param  list<OtherName>  $otherNames
     */
    public function __construct(
        public ?PersonName $name = null,
        public array $otherNames = [],
        public ?Biography $biography = null,
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
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
        );
    }
}
