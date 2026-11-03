<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifier;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\ExternalIdentifiers\GetPersonExternalIdentifier;
use VincentAuger\OrcidSdk\Requests\ExternalIdentifiers\ListPersonExternalIdentifiers;

/**
 * Reads on the external identifiers of any record: $orcid->record($id)->externalIdentifiers().
 * Every method throws an OrcidException on failure.
 */
final readonly class ReadOnlyExternalIdentifiersResource
{
    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {}

    /**
     * The record's external identifiers visible to the token, in the order the researcher chose
     *
     * @return list<PersonExternalIdentifier>
     */
    public function list(): array
    {
        $request = new ListPersonExternalIdentifiers($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * One external identifier, by put-code
     */
    public function get(int $putCode): PersonExternalIdentifier
    {
        $request = new GetPersonExternalIdentifier($this->orcid, $putCode);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }
}
