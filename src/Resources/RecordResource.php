<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Activities\ActivitiesSummary;
use VincentAuger\OrcidSdk\Data\Person\Biography;
use VincentAuger\OrcidSdk\Data\Person\Email;
use VincentAuger\OrcidSdk\Data\Person\Person;
use VincentAuger\OrcidSdk\Data\Person\PersonalDetails;
use VincentAuger\OrcidSdk\Data\Record\Record;
use VincentAuger\OrcidSdk\Data\Record\RecordSummary;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Activities\GetActivitiesSummary;
use VincentAuger\OrcidSdk\Requests\Person\GetBiography;
use VincentAuger\OrcidSdk\Requests\Person\GetPerson;
use VincentAuger\OrcidSdk\Requests\Person\GetPersonalDetails;
use VincentAuger\OrcidSdk\Requests\Person\ListEmails;
use VincentAuger\OrcidSdk\Requests\Record\GetRecord;
use VincentAuger\OrcidSdk\Requests\Record\GetRecordSummary;

/**
 * Reads on any ORCID record: $orcid->record('0000-0002-1825-0097')->... Writes go
 * through a member connector bound to its record (Orcid::member(accessToken:, orcid:)).
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
     * The biography alone: the same Biography as person()->biography
     */
    public function biography(): ?Biography
    {
        $request = new GetBiography($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * The emails visible to the token (limited ones need /read-limited): the same Email items as person()->emails
     *
     * @return list<Email>
     */
    public function emails(): array
    {
        $request = new ListEmails($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * The name, other names and biography only: the same classes as person()
     */
    public function personalDetails(): PersonalDetails
    {
        $request = new GetPersonalDetails($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Summary of every activity on the record: affiliations, and works, fundings, peer reviews and research
     * resources as groups, the same classes as each section's list()
     */
    public function activities(): ActivitiesSummary
    {
        $request = new GetActivitiesSummary($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * The whole record in one call: the same Person and ActivitiesSummary as person() and activities()
     */
    public function wholeRecord(): Record
    {
        $request = new GetRecord($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * ORCID's short overview of the record: counts by section, validated or self-asserted. Member API only (any
     * token that reads the record, e.g. /read-public).
     *
     * @throws \LogicException on a Public API connector, before anything is sent
     */
    public function recordSummary(): RecordSummary
    {
        $request = new GetRecordSummary($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function keywords(): ReadOnlyKeywordsResource
    {
        return new ReadOnlyKeywordsResource($this->connector, $this->orcid);
    }

    public function otherNames(): ReadOnlyOtherNamesResource
    {
        return new ReadOnlyOtherNamesResource($this->connector, $this->orcid);
    }

    public function researcherUrls(): ReadOnlyResearcherUrlsResource
    {
        return new ReadOnlyResearcherUrlsResource($this->connector, $this->orcid);
    }

    public function externalIdentifiers(): ReadOnlyExternalIdentifiersResource
    {
        return new ReadOnlyExternalIdentifiersResource($this->connector, $this->orcid);
    }

    public function addresses(): ReadOnlyAddressesResource
    {
        return new ReadOnlyAddressesResource($this->connector, $this->orcid);
    }

    public function affiliations(): ReadOnlyAffiliationsResource
    {
        return new ReadOnlyAffiliationsResource($this->connector, $this->orcid);
    }

    public function works(): ReadOnlyWorksResource
    {
        return new ReadOnlyWorksResource($this->connector, $this->orcid);
    }

    public function fundings(): ReadOnlyFundingsResource
    {
        return new ReadOnlyFundingsResource($this->connector, $this->orcid);
    }

    public function peerReviews(): ReadOnlyPeerReviewsResource
    {
        return new ReadOnlyPeerReviewsResource($this->connector, $this->orcid);
    }

    public function researchResources(): ReadOnlyResearchResourcesResource
    {
        return new ReadOnlyResearchResourcesResource($this->connector, $this->orcid);
    }
}
