<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifier;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifierInput;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\ExternalIdentifiers\CreatePersonExternalIdentifier;
use VincentAuger\OrcidSdk\Requests\ExternalIdentifiers\DeletePersonExternalIdentifier;
use VincentAuger\OrcidSdk\Requests\ExternalIdentifiers\UpdatePersonExternalIdentifier;

/**
 * Person external identifiers (identifiers in other systems) of the record a member connector is bound to:
 * $orcid->externalIdentifiers(). Writes need the /person/update scope. Every method throws an OrcidException on failure.
 */
final readonly class ExternalIdentifiersResource
{
    private ReadOnlyExternalIdentifiersResource $readOnly;

    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {
        $this->readOnly = new ReadOnlyExternalIdentifiersResource($connector, $orcid);
    }

    /**
     * The record's external identifiers visible to the token, in the order the researcher chose
     *
     * @return list<PersonExternalIdentifier>
     */
    public function list(): array
    {
        return $this->readOnly->list();
    }

    /**
     * One external identifier, by put-code
     */
    public function get(int $putCode): PersonExternalIdentifier
    {
        return $this->readOnly->get($putCode);
    }

    /**
     * @return int The put-code of the new item: store it to update or delete the item later
     *
     * @throws OrcidConflictException when this client already added an external identifier with the same type and value
     *                                (`isDuplicate()`; ORCID does not give its put-code)
     */
    public function create(PersonExternalIdentifierInput $input): int
    {
        $request = new CreatePersonExternalIdentifier($this->orcid, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Replace an external identifier created by this client
     *
     * @throws OrcidConflictException when this client already added another identifier with the new type and value
     */
    public function update(int $putCode, PersonExternalIdentifierInput $input): PersonExternalIdentifier
    {
        $request = new UpdatePersonExternalIdentifier($this->orcid, $putCode, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function delete(int $putCode): void
    {
        $this->connector->sendOrFail(new DeletePersonExternalIdentifier($this->orcid, $putCode));
    }
}
