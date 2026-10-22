<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Activities\ActivitiesSummary;
use VincentAuger\OrcidSdk\Data\Person\Person;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Activities\GetActivitiesSummary;
use VincentAuger\OrcidSdk\Requests\Person\GetPerson;

/**
 * Calls scoped to one ORCID record: $orcid->record('0000-0002-1825-0097')->...
 */
final readonly class RecordResource
{
    public function __construct(
        private Orcid $connector,
        /** The normalised ORCID iD */
        public string $orcid,
    ) {}

    /**
     * The person section: names, biography, emails, keywords, websites, ...
     */
    public function person(): Person
    {
        $request = new GetPerson($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Summary of every activity on the record
     */
    public function activities(): ActivitiesSummary
    {
        $request = new GetActivitiesSummary($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function affiliations(): AffiliationsResource
    {
        return new AffiliationsResource($this->connector, $this->orcid);
    }
}
