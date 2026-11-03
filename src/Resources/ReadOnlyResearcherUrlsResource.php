<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Person\ResearcherUrl;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\ResearcherUrls\GetResearcherUrl;
use VincentAuger\OrcidSdk\Requests\ResearcherUrls\ListResearcherUrls;

/**
 * Reads on the researcher URLs of any record: $orcid->record($id)->researcherUrls().
 * Every method throws an OrcidException on failure.
 */
final readonly class ReadOnlyResearcherUrlsResource
{
    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {}

    /**
     * The record's researcher URLs visible to the token, in the order the researcher chose
     *
     * @return list<ResearcherUrl>
     */
    public function list(): array
    {
        $request = new ListResearcherUrls($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * One researcher URL, by put-code
     */
    public function get(int $putCode): ResearcherUrl
    {
        $request = new GetResearcherUrl($this->orcid, $putCode);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }
}
