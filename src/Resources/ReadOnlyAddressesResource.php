<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Person\Address;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Addresses\GetAddress;
use VincentAuger\OrcidSdk\Requests\Addresses\ListAddresses;

/**
 * Reads on the addresses (countries) of any record: $orcid->record($id)->addresses().
 * Every method throws an OrcidException on failure.
 */
final readonly class ReadOnlyAddressesResource
{
    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {}

    /**
     * The record's addresses visible to the token, in the order the researcher chose
     *
     * @return list<Address>
     */
    public function list(): array
    {
        $request = new ListAddresses($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * One address, by put-code
     */
    public function get(int $putCode): Address
    {
        $request = new GetAddress($this->orcid, $putCode);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }
}
