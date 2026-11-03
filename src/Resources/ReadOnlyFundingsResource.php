<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Fundings\Funding;
use VincentAuger\OrcidSdk\Data\Fundings\FundingGroups;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Fundings\GetFunding;
use VincentAuger\OrcidSdk\Requests\Fundings\ListFundings;

/**
 * Reads on the fundings of any record: $orcid->record($id)->fundings().
 * Every method throws an OrcidException on failure.
 */
final readonly class ReadOnlyFundingsResource
{
    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {}

    /**
     * The record's fundings as groups: preferred() to display them, summaries() to sync
     */
    public function list(): FundingGroups
    {
        $request = new ListFundings($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * One funding in full, by put-code
     */
    public function get(int $putCode): Funding
    {
        $request = new GetFunding($this->orcid, $putCode);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }
}
