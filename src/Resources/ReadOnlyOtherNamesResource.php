<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Person\OtherName;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\OtherNames\GetOtherName;
use VincentAuger\OrcidSdk\Requests\OtherNames\ListOtherNames;

/**
 * Reads on the other names of any record: $orcid->record($id)->otherNames().
 * Every method throws an OrcidException on failure.
 */
final readonly class ReadOnlyOtherNamesResource
{
    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {}

    /**
     * The record's other names visible to the token, in the order the researcher chose
     *
     * @return list<OtherName>
     */
    public function list(): array
    {
        $request = new ListOtherNames($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * One other name, by put-code
     */
    public function get(int $putCode): OtherName
    {
        $request = new GetOtherName($this->orcid, $putCode);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }
}
