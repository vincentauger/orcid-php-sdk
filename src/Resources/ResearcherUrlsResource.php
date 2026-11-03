<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Person\ResearcherUrl;
use VincentAuger\OrcidSdk\Data\Person\ResearcherUrlInput;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\ResearcherUrls\CreateResearcherUrl;
use VincentAuger\OrcidSdk\Requests\ResearcherUrls\DeleteResearcherUrl;
use VincentAuger\OrcidSdk\Requests\ResearcherUrls\UpdateResearcherUrl;

/**
 * Researcher URLs (websites) of the record a member connector is bound to: $orcid->researcherUrls().
 * Writes need the /person/update scope. Every method throws an OrcidException on failure.
 */
final readonly class ResearcherUrlsResource
{
    private ReadOnlyResearcherUrlsResource $readOnly;

    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {
        $this->readOnly = new ReadOnlyResearcherUrlsResource($connector, $orcid);
    }

    /**
     * The record's researcher URLs visible to the token, in the order the researcher chose
     *
     * @return list<ResearcherUrl>
     */
    public function list(): array
    {
        return $this->readOnly->list();
    }

    /**
     * One researcher URL, by put-code
     */
    public function get(int $putCode): ResearcherUrl
    {
        return $this->readOnly->get($putCode);
    }

    /**
     * @return int The put-code of the new item: store it to update or delete the item later
     *
     * @throws OrcidConflictException when this client already added a researcher URL with the same URL
     *                                (`isDuplicate()`; ORCID does not give its put-code)
     */
    public function create(ResearcherUrlInput $input): int
    {
        $request = new CreateResearcherUrl($this->orcid, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Replace a researcher URL created by this client
     *
     * @throws OrcidConflictException when this client already added another researcher URL with the new URL
     */
    public function update(int $putCode, ResearcherUrlInput $input): ResearcherUrl
    {
        $request = new UpdateResearcherUrl($this->orcid, $putCode, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function delete(int $putCode): void
    {
        $this->connector->sendOrFail(new DeleteResearcherUrl($this->orcid, $putCode));
    }
}
