<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Person\OtherName;
use VincentAuger\OrcidSdk\Data\Person\OtherNameInput;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\OtherNames\CreateOtherName;
use VincentAuger\OrcidSdk\Requests\OtherNames\DeleteOtherName;
use VincentAuger\OrcidSdk\Requests\OtherNames\UpdateOtherName;

/**
 * Other names of the record a member connector is bound to: $orcid->otherNames().
 * Writes need the /person/update scope. Every method throws an OrcidException on failure.
 */
final readonly class OtherNamesResource
{
    private ReadOnlyOtherNamesResource $readOnly;

    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {
        $this->readOnly = new ReadOnlyOtherNamesResource($connector, $orcid);
    }

    /**
     * The record's other names visible to the token, in the order the researcher chose
     *
     * @return list<OtherName>
     */
    public function list(): array
    {
        return $this->readOnly->list();
    }

    /**
     * One other name, by put-code
     */
    public function get(int $putCode): OtherName
    {
        return $this->readOnly->get($putCode);
    }

    /**
     * @return int The put-code of the new item: store it to update or delete the item later
     *
     * @throws OrcidConflictException when this client already added an other name with the same content
     *                                (`isDuplicate()`; ORCID does not give its put-code)
     */
    public function create(OtherNameInput $input): int
    {
        $request = new CreateOtherName($this->orcid, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Replace an other name created by this client
     *
     * @throws OrcidConflictException when this client already added an other name with the new content
     */
    public function update(int $putCode, OtherNameInput $input): OtherName
    {
        $request = new UpdateOtherName($this->orcid, $putCode, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function delete(int $putCode): void
    {
        $this->connector->sendOrFail(new DeleteOtherName($this->orcid, $putCode));
    }
}
