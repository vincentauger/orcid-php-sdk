<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Record;

use VincentAuger\OrcidSdk\Data\Activities\ActivitiesSummary;
use VincentAuger\OrcidSdk\Data\Person\Person;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * The whole record (GET /{orcid}/record): the person and activities sections in one call, as the same classes as
 * person() and activities(). Only data visible to the token is returned: public data, plus limited data with
 * /read-limited.
 */
final readonly class Record
{
    public function __construct(
        /** The record's ORCID iD */
        public string $orcid,
        public Person $person = new Person,
        public ActivitiesSummary $activities = new ActivitiesSummary,
        public ?RecordHistory $history = null,
        /** The researcher's language on orcid.org, e.g. "en" or "fr" */
        public ?string $locale = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orcid: Json::string(Json::object($data, 'orcid-identifier') ?? [], 'path')
                ?? throw new \UnexpectedValueException('ORCID record has no orcid-identifier'),
            person: Person::fromArray(Json::object($data, 'person') ?? []),
            activities: ActivitiesSummary::fromArray(Json::object($data, 'activities-summary') ?? []),
            history: RecordHistory::fromArray(Json::object($data, 'history')),
            locale: Json::string(Json::object($data, 'preferences') ?? [], 'locale'),
        );
    }
}
