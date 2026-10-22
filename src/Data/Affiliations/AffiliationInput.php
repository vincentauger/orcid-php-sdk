<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Affiliations;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Data to create or update an affiliation (any AffiliationType).
 *
 * ORCID replaces the whole item on update, so every field you want to keep
 * must be sent again. Blank optional strings are treated as not set; blank
 * required values throw, because ORCID would reject them (error 9047).
 *
 * Required by ORCID: an organization with a name, a city, a country and a
 * disambiguated organization (errors 9045, 9046, 9060).
 */
final readonly class AffiliationInput
{
    public ?string $departmentName;

    public ?string $roleTitle;

    public ?string $url;

    /**
     * @param  list<ExternalId>  $externalIds
     */
    public function __construct(
        public Organization $organization,
        ?string $departmentName = null,
        ?string $roleTitle = null,
        public ?FuzzyDate $startDate = null,
        public ?FuzzyDate $endDate = null,
        ?string $url = null,
        public array $externalIds = [],
    ) {
        $this->departmentName = Json::blankToNull($departmentName);
        $this->roleTitle = Json::blankToNull($roleTitle);
        $this->url = Json::blankToNull($url);

        $this->require($organization->name, 'organization name');
        $this->require($organization->address?->city, 'organization city');
        $this->require($organization->address?->country, 'organization country');

        if (! $organization->disambiguatedOrganization instanceof DisambiguatedOrganization) {
            throw new \InvalidArgumentException('ORCID requires a disambiguated organization (e.g. a ROR identifier)');
        }

        if ($startDate instanceof FuzzyDate && $endDate instanceof FuzzyDate && $endDate->isBefore($startDate)) {
            throw new \InvalidArgumentException(sprintf('The end date (%s) is before the start date (%s)', $endDate, $startDate));
        }
    }

    /**
     * ORCID JSON body. For an update, pass the item's put-code.
     *
     * @return array<string, mixed>
     */
    public function toArray(?int $putCode = null): array
    {
        return Json::withoutNulls([
            'put-code' => $putCode,
            'department-name' => $this->departmentName,
            'role-title' => $this->roleTitle,
            'start-date' => $this->startDate?->toArray(),
            'end-date' => $this->endDate?->toArray(),
            'organization' => $this->organization->toArray(),
            'url' => Json::wrap($this->url),
            'external-ids' => ExternalId::listToArray($this->externalIds),
        ]);
    }

    private function require(?string $value, string $field): void
    {
        if (Json::blankToNull($value) === null) {
            throw new \InvalidArgumentException(sprintf('ORCID requires a non-blank %s', $field));
        }
    }
}
