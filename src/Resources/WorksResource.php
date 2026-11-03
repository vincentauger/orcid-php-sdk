<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Works\Work;
use VincentAuger\OrcidSdk\Data\Works\WorkGroups;
use VincentAuger\OrcidSdk\Data\Works\WorkInput;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Works\CreateWork;
use VincentAuger\OrcidSdk\Requests\Works\DeleteWork;
use VincentAuger\OrcidSdk\Requests\Works\UpdateWork;

/**
 * Works of the record a member connector is bound to: $orcid->works().
 * Every method throws an OrcidException on failure.
 */
final readonly class WorksResource
{
    private ReadOnlyWorksResource $readOnly;

    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {
        $this->readOnly = new ReadOnlyWorksResource($connector, $orcid);
    }

    /**
     * The record's works as groups: preferred() to display them, summaries() to sync
     */
    public function list(): WorkGroups
    {
        return $this->readOnly->list();
    }

    /**
     * One work in full, by put-code
     */
    public function get(int $putCode): Work
    {
        return $this->readOnly->get($putCode);
    }

    /**
     * @return int The put-code of the new item: store it to update or delete the item later
     *
     * @throws OrcidConflictException when this client already added a work with
     *                                the same `self` external identifier
     *                                (`isDuplicate()`, `existingPutCode()`)
     */
    public function create(WorkInput $input): int
    {
        $request = new CreateWork($this->orcid, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Replace a work created by this client. Fields left out of $input are cleared.
     */
    public function update(int $putCode, WorkInput $input): Work
    {
        $request = new UpdateWork($this->orcid, $putCode, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function delete(int $putCode): void
    {
        $this->connector->sendOrFail(new DeleteWork($this->orcid, $putCode));
    }
}
