<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Affiliations;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * An affiliation as returned by ORCID (a single item or a list summary;
 * both have the same fields). Use AffiliationInput to create or update one.
 */
final readonly class Affiliation
{
    /**
     * @param  list<ExternalId>  $externalIds
     */
    public function __construct(
        public AffiliationType $type,
        public int $putCode,
        public ?Organization $organization,
        public ?string $departmentName = null,
        public ?string $roleTitle = null,
        public ?FuzzyDate $startDate = null,
        public ?FuzzyDate $endDate = null,
        public ?string $url = null,
        public array $externalIds = [],
        public ?Source $source = null,
        public ?string $visibility = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(AffiliationType $type, array $data): self
    {
        return new self(
            type: $type,
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID affiliation has no put-code'),
            organization: Organization::fromArray(Json::object($data, 'organization')),
            departmentName: Json::string($data, 'department-name'),
            roleTitle: Json::string($data, 'role-title'),
            startDate: FuzzyDate::fromArray(Json::object($data, 'start-date')),
            endDate: FuzzyDate::fromArray(Json::object($data, 'end-date')),
            url: Json::string($data, 'url'),
            externalIds: ExternalId::listFromArray(Json::object($data, 'external-ids')),
            source: Source::fromArray(Json::object($data, 'source')),
            visibility: Json::string($data, 'visibility'),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
        );
    }

    /**
     * Read a list response ({"affiliation-group": [{"summaries": [...]}]}).
     * Groups (items ORCID considers duplicates) are flattened.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<self>
     */
    public static function listFromArray(AffiliationType $type, array $data): array
    {
        $items = [];

        foreach (Json::list($data, 'affiliation-group') as $group) {
            foreach (Json::list($group, 'summaries') as $summary) {
                $item = Json::object($summary, $type->summaryKey());

                if ($item !== null) {
                    $items[] = self::fromArray($type, $item);
                }
            }
        }

        return $items;
    }
}
