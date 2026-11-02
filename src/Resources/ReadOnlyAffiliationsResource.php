<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Affiliations\GetAffiliation;
use VincentAuger\OrcidSdk\Requests\Affiliations\ListAffiliations;

/**
 * Reads on the affiliations of any record: $orcid->record($id)->affiliations().
 * Every method throws an OrcidException on failure.
 */
final readonly class ReadOnlyAffiliationsResource
{
    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {}

    /**
     * @return list<Affiliation>
     */
    public function list(AffiliationType $type): array
    {
        $request = new ListAffiliations($this->orcid, $type);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function get(AffiliationType $type, int $putCode): Affiliation
    {
        $request = new GetAffiliation($this->orcid, $type, $putCode);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }
}
