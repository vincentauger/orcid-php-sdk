<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Person\Address;
use VincentAuger\OrcidSdk\Data\Person\AddressInput;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Addresses\CreateAddress;
use VincentAuger\OrcidSdk\Requests\Addresses\DeleteAddress;
use VincentAuger\OrcidSdk\Requests\Addresses\UpdateAddress;

/**
 * Addresses (countries) of the record a member connector is bound to: $orcid->addresses().
 * Writes need the /person/update scope. Every method throws an OrcidException on failure.
 */
final readonly class AddressesResource
{
    private ReadOnlyAddressesResource $readOnly;

    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {
        $this->readOnly = new ReadOnlyAddressesResource($connector, $orcid);
    }

    /**
     * The record's addresses (countries) visible to the token, in the order the researcher chose
     *
     * @return list<Address>
     */
    public function list(): array
    {
        return $this->readOnly->list();
    }

    /**
     * One address, by put-code
     */
    public function get(int $putCode): Address
    {
        return $this->readOnly->get($putCode);
    }

    /**
     * ORCID accepts a country already listed, even one this client added
     *
     * @return int The put-code of the new item: store it to update or delete the item later
     */
    public function create(AddressInput $input): int
    {
        $request = new CreateAddress($this->orcid, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Replace an address created by this client
     */
    public function update(int $putCode, AddressInput $input): Address
    {
        $request = new UpdateAddress($this->orcid, $putCode, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function delete(int $putCode): void
    {
        $this->connector->sendOrFail(new DeleteAddress($this->orcid, $putCode));
    }
}
