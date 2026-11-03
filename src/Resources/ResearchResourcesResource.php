<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResource;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceGroups;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceInput;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\ResearchResources\CreateResearchResource;
use VincentAuger\OrcidSdk\Requests\ResearchResources\DeleteResearchResource;
use VincentAuger\OrcidSdk\Requests\ResearchResources\UpdateResearchResource;

/**
 * Research resources of the record a member connector is bound to: $orcid->researchResources().
 * Every method throws an OrcidException on failure.
 */
final readonly class ResearchResourcesResource
{
    private ReadOnlyResearchResourcesResource $readOnly;

    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {
        $this->readOnly = new ReadOnlyResearchResourcesResource($connector, $orcid);
    }

    /**
     * The record's research resources as groups: preferred() to display them, summaries() to sync
     */
    public function list(): ResearchResourceGroups
    {
        return $this->readOnly->list();
    }

    /**
     * One research resource in full, by put-code
     */
    public function get(int $putCode): ResearchResource
    {
        return $this->readOnly->get($putCode);
    }

    /**
     * @return int The put-code of the new item: store it to update or delete the item later
     *
     * @throws OrcidConflictException when this client already added a research resource with
     *                                the same `self` proposal identifier
     *                                (`isDuplicate()`, `existingPutCode()`)
     */
    public function create(ResearchResourceInput $input): int
    {
        $request = new CreateResearchResource($this->orcid, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Replace a research resource created by this client. Fields left out of $input are cleared.
     */
    public function update(int $putCode, ResearchResourceInput $input): ResearchResource
    {
        $request = new UpdateResearchResource($this->orcid, $putCode, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function delete(int $putCode): void
    {
        $this->connector->sendOrFail(new DeleteResearchResource($this->orcid, $putCode));
    }
}
