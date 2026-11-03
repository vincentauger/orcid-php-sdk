<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Fundings;

use VincentAuger\OrcidSdk\Data\Common\Amount;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Enums\FundingType;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * A funding as returned by ORCID, read in full by put-code. Use FundingInput to create or update one.
 *
 * Reading never fails on what ORCID returns: a type the SDK does not know is kept in `rawType`, with a null `type`.
 */
final readonly class Funding
{
    /**
     * @param  Organization|null  $organization  The funder
     * @param  list<ExternalId>  $externalIds
     * @param  list<FundingContributor>  $contributors
     */
    public function __construct(
        public int $putCode,
        public ?Title $title,
        public ?FundingType $type,
        public ?string $rawType,
        public ?Organization $organization = null,
        public ?string $organizationDefinedType = null,
        public ?string $shortDescription = null,
        public ?Amount $amount = null,
        public ?Url $url = null,
        public ?FuzzyDate $startDate = null,
        public ?FuzzyDate $endDate = null,
        public array $externalIds = [],
        public array $contributors = [],
        public ?Source $source = null,
        public ?Visibility $visibility = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID funding has no put-code'),
            title: Title::fromArray(Json::object($data, 'title')),
            type: Json::enum($data, 'type', FundingType::class),
            rawType: Json::string($data, 'type'),
            organization: Organization::fromArray(Json::object($data, 'organization')),
            organizationDefinedType: Json::string($data, 'organization-defined-type'),
            shortDescription: Json::string($data, 'short-description'),
            amount: Amount::fromArray(Json::object($data, 'amount')),
            url: Url::fromArray(Json::object($data, 'url')),
            startDate: FuzzyDate::fromArray(Json::object($data, 'start-date')),
            endDate: FuzzyDate::fromArray(Json::object($data, 'end-date')),
            externalIds: ExternalId::listFromArray(Json::object($data, 'external-ids')),
            contributors: FundingContributor::listFromArray(Json::object($data, 'contributors')),
            source: Source::fromArray(Json::object($data, 'source')),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
        );
    }
}
