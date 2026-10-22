<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Data\Affiliations\AffiliationInput;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Affiliations\CreateAffiliation;
use VincentAuger\OrcidSdk\Requests\Affiliations\DeleteAffiliation;
use VincentAuger\OrcidSdk\Requests\Affiliations\GetAffiliation;
use VincentAuger\OrcidSdk\Requests\Affiliations\ListAffiliations;
use VincentAuger\OrcidSdk\Requests\Affiliations\UpdateAffiliation;

/**
 * Employments, educations, qualifications, invited positions, distinctions,
 * memberships and services of a record. Every method throws an OrcidException
 * on failure.
 */
final readonly class AffiliationsResource
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

    /**
     * @return int The put-code of the new item: store it to update or delete the item later
     */
    public function create(AffiliationType $type, AffiliationInput $input): int
    {
        $request = new CreateAffiliation($this->orcid, $type, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Replace an item created by this client. Fields left out of $input are cleared.
     */
    public function update(AffiliationType $type, int $putCode, AffiliationInput $input): Affiliation
    {
        $request = new UpdateAffiliation($this->orcid, $type, $putCode, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function delete(AffiliationType $type, int $putCode): void
    {
        $this->connector->sendOrFail(new DeleteAffiliation($this->orcid, $type, $putCode));
    }
}
