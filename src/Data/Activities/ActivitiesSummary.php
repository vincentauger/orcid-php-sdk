<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Activities;

use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Summary of every activity on a record (GET /{orcid}/activities).
 *
 * Affiliations are fully typed. Works, fundings, peer reviews and research
 * resources are only counted for now: each count is the number of groups,
 * i.e. distinct items once ORCID has merged duplicates from different sources.
 */
final readonly class ActivitiesSummary
{
    /**
     * @param  array<string, list<Affiliation>>  $affiliations  Keyed by AffiliationType value
     */
    public function __construct(
        private array $affiliations = [],
        public int $workCount = 0,
        public int $fundingCount = 0,
        public int $peerReviewCount = 0,
        public int $researchResourceCount = 0,
        public ?\DateTimeImmutable $lastModifiedAt = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $affiliations = [];

        foreach (AffiliationType::cases() as $type) {
            $affiliations[$type->value] = Affiliation::listFromArray($type, Json::object($data, $type->listEndpoint()) ?? []);
        }

        return new self(
            affiliations: $affiliations,
            workCount: self::groupCount($data, 'works'),
            fundingCount: self::groupCount($data, 'fundings'),
            peerReviewCount: self::groupCount($data, 'peer-reviews'),
            researchResourceCount: self::groupCount($data, 'research-resources'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
        );
    }

    /**
     * @return list<Affiliation>
     */
    public function affiliations(AffiliationType $type): array
    {
        return $this->affiliations[$type->value] ?? [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function groupCount(array $data, string $section): int
    {
        return count(Json::list(Json::object($data, $section) ?? [], 'group'));
    }
}
