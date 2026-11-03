<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Fundings\Funding;
use VincentAuger\OrcidSdk\Data\Fundings\FundingGroups;
use VincentAuger\OrcidSdk\Data\Fundings\FundingInput;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Fundings\CreateFunding;
use VincentAuger\OrcidSdk\Requests\Fundings\DeleteFunding;
use VincentAuger\OrcidSdk\Requests\Fundings\UpdateFunding;

/**
 * Fundings of the record a member connector is bound to: $orcid->fundings().
 * Every method throws an OrcidException on failure.
 */
final readonly class FundingsResource
{
    private ReadOnlyFundingsResource $readOnly;

    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {
        $this->readOnly = new ReadOnlyFundingsResource($connector, $orcid);
    }

    /**
     * The record's fundings as groups: preferred() to display them, summaries() to sync
     */
    public function list(): FundingGroups
    {
        return $this->readOnly->list();
    }

    /**
     * One funding in full, by put-code
     */
    public function get(int $putCode): Funding
    {
        return $this->readOnly->get($putCode);
    }

    /**
     * @return int The put-code of the new item: store it to update or delete the item later
     *
     * @throws OrcidConflictException when this client already added a funding with
     *                                the same `self` external identifier
     *                                (`isDuplicate()`, `existingPutCode()`)
     */
    public function create(FundingInput $input): int
    {
        $request = new CreateFunding($this->orcid, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Replace a funding created by this client. Fields left out of $input are cleared.
     */
    public function update(int $putCode, FundingInput $input): Funding
    {
        $request = new UpdateFunding($this->orcid, $putCode, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function delete(int $putCode): void
    {
        $this->connector->sendOrFail(new DeleteFunding($this->orcid, $putCode));
    }
}
