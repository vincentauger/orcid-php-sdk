<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Fundings;

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
 * A funding as listed in a group: no amount, contributors, description or organization-defined type (`c2/f38`).
 * Read the full funding by put-code.
 *
 * Reading never fails on what ORCID returns: a type the SDK does not know is kept in `rawType`, with a null `type`.
 */
final readonly class FundingSummary
{
    /**
     * @param  list<ExternalId>  $externalIds
     * @param  Organization|null  $organization  The funder
     */
    public function __construct(
        public int $putCode,
        public ?Title $title,
        public ?FundingType $type,
        public ?string $rawType,
        public array $externalIds = [],
        public ?Organization $organization = null,
        public ?FuzzyDate $startDate = null,
        public ?FuzzyDate $endDate = null,
        public ?Url $url = null,
        public ?Source $source = null,
        public ?Visibility $visibility = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
        /** The highest display index in a group marks the preferred summary (FundingGroup::preferred()) */
        public ?int $displayIndex = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID funding summary has no put-code'),
            title: Title::fromArray(Json::object($data, 'title')),
            type: Json::enum($data, 'type', FundingType::class),
            rawType: Json::string($data, 'type'),
            externalIds: ExternalId::listFromArray(Json::object($data, 'external-ids')),
            organization: Organization::fromArray(Json::object($data, 'organization')),
            startDate: FuzzyDate::fromArray(Json::object($data, 'start-date')),
            endDate: FuzzyDate::fromArray(Json::object($data, 'end-date')),
            url: Url::fromArray(Json::object($data, 'url')),
            source: Source::fromArray(Json::object($data, 'source')),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
            displayIndex: Json::int($data, 'display-index'),
        );
    }
}
