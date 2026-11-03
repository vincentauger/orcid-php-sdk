<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Fundings;

use VincentAuger\OrcidSdk\Data\Common\Amount;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\TranslatedTitle;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Enums\FundingContributorRole;
use VincentAuger\OrcidSdk\Enums\FundingType;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Data to create or update a funding.
 *
 * ORCID replaces the whole item on update, so every field you want to keep must be sent again. Blank optional
 * strings are treated as not set.
 *
 * Required by ORCID: a title without subtitle (error 9022), a type, an external identifier of any relationship
 * (9023; unlike a work, `self` is not required), and the funder: an organization with a name, an address and a
 * disambiguated organization (9047, 9045). A start date is required whenever an end date is given (9050). Sending
 * a `self` identifier this client already used fails (409, error 9021).
 */
final readonly class FundingInput
{
    public ?string $organizationDefinedType;

    public ?string $shortDescription;

    /**
     * @param  Organization  $organization  The funder
     * @param  list<ExternalId>  $externalIds  Usually the grant number (type "grant_number"), as `self`
     * @param  string|null  $organizationDefinedType  The funder's own name for the type, e.g. "Discovery grant"
     * @param  list<FundingContributor>  $contributors
     */
    public function __construct(
        public Title $title,
        public FundingType $type,
        public Organization $organization,
        public array $externalIds,
        ?string $organizationDefinedType = null,
        ?string $shortDescription = null,
        public ?Amount $amount = null,
        public ?Url $url = null,
        public ?FuzzyDate $startDate = null,
        public ?FuzzyDate $endDate = null,
        public array $contributors = [],
    ) {
        $this->organizationDefinedType = Json::blankToNull($organizationDefinedType);
        $this->shortDescription = Json::blankToNull($shortDescription);

        if ($externalIds === []) {
            throw new \InvalidArgumentException('ORCID requires an external identifier, e.g. the grant number (error 9023)');
        }

        if (Json::blankToNull($organization->name) === null) {
            throw new \InvalidArgumentException('ORCID requires a non-blank organization name (error 9047)');
        }

        // ORCID only requires the address element, but an empty one would be sent as a JSON list
        if (Json::blankToNull($organization->address?->city) === null && Json::blankToNull($organization->address?->country) === null) {
            throw new \InvalidArgumentException('ORCID requires an organization address, with a city or a country (error 9047)');
        }

        if (! $organization->disambiguatedOrganization instanceof DisambiguatedOrganization) {
            throw new \InvalidArgumentException('ORCID requires a disambiguated organization, e.g. a ROR identifier (error 9045)');
        }

        if ($endDate instanceof FuzzyDate && ! $startDate instanceof FuzzyDate) {
            throw new \InvalidArgumentException('ORCID requires a start date when an end date is given (error 9050)');
        }

        if ($startDate instanceof FuzzyDate && $endDate instanceof FuzzyDate && $endDate->isBefore($startDate)) {
            throw new \InvalidArgumentException(sprintf('The end date (%s) is before the start date (%s)', $endDate, $startDate));
        }

        if ($title->subtitle !== null) {
            throw new \InvalidArgumentException('A funding title has no subtitle: ORCID refuses one (error 9047)');
        }

        // ORCID's schema limits (error 9047)
        $this->assertMaxLength($title->title, 1000, 'title');
        $this->assertMaxLength($title->translatedTitle?->title, 1000, 'translated title');
        $this->assertMaxLength($this->organizationDefinedType, 255, 'organization-defined type');
        $this->assertMaxLength($this->shortDescription, 5000, 'short description');

        // ORCID codes are ISO 639-1, plus zh_CN and zh_TW; ORCID also rejects unknown ones (error 9047)
        if ($title->translatedTitle instanceof TranslatedTitle && preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $title->translatedTitle->languageCode) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid language code "%s", expected e.g. "en" or "zh_CN"', $title->translatedTitle->languageCode));
        }

        foreach ($contributors as $contributor) {
            // ORCID rejects other roles (error 9058)
            if (! $contributor->role instanceof FundingContributorRole && $contributor->rawRole !== null) {
                throw new \InvalidArgumentException(sprintf('Unknown contributor role "%s": use a FundingContributorRole', $contributor->rawRole));
            }

            $this->assertMaxLength($contributor->creditName, 150, 'contributor credit name');
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
            'type' => $this->type->value,
            'organization-defined-type' => Json::wrap($this->organizationDefinedType),
            'title' => $this->title->toArray(),
            'short-description' => $this->shortDescription,
            'amount' => $this->amount?->toArray(),
            'url' => $this->url?->toArray(),
            'start-date' => $this->startDate?->toArray(),
            'end-date' => $this->endDate?->toArray(),
            'external-ids' => ExternalId::listToArray($this->externalIds),
            'contributors' => FundingContributor::listToArray($this->contributors),
            'organization' => $this->organization->toArray(),
        ]);
    }

    private function assertMaxLength(?string $value, int $max, string $field): void
    {
        if ($value !== null && mb_strlen($value) > $max) {
            throw new \InvalidArgumentException(sprintf('The %s is longer than %d characters', $field, $max));
        }
    }
}
