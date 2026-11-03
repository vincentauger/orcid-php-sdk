<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResource;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceGroups;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\ResearchResources\GetResearchResource;
use VincentAuger\OrcidSdk\Requests\ResearchResources\ListResearchResources;

/**
 * Reads on the research resources of any record: $orcid->record($id)->researchResources().
 * Every method throws an OrcidException on failure.
 */
final readonly class ReadOnlyResearchResourcesResource
{
    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {}

    /**
     * The record's research resources as groups: preferred() to display them, summaries() to sync
     */
    public function list(): ResearchResourceGroups
    {
        $request = new ListResearchResources($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * One research resource in full, by put-code
     */
    public function get(int $putCode): ResearchResource
    {
        $request = new GetResearchResource($this->orcid, $putCode);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }
}
