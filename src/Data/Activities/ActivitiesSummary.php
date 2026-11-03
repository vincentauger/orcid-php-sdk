<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Activities;

use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Data\Fundings\FundingGroups;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewGroups;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceGroups;
use VincentAuger\OrcidSdk\Data\Works\WorkGroups;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Summary of every activity on a record (GET /{orcid}/activities, and the activities section of the whole record).
 *
 * Each section is the same class as its own list(): affiliations by type, and the groups of works, fundings, peer
 * reviews and research resources. Each count is the number of ORCID's groups, i.e. distinct items once ORCID has
 * merged duplicates from different sources (review groups, for peer reviews).
 */
final readonly class ActivitiesSummary
{
    /**
     * @param  array<string, list<Affiliation>>  $affiliations  Keyed by AffiliationType value
     */
    public function __construct(
        private array $affiliations = [],
        public WorkGroups $works = new WorkGroups,
        public FundingGroups $fundings = new FundingGroups,
        public PeerReviewGroups $peerReviews = new PeerReviewGroups,
        public ResearchResourceGroups $researchResources = new ResearchResourceGroups,
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
            works: WorkGroups::fromArray(Json::object($data, 'works') ?? []),
            fundings: FundingGroups::fromArray(Json::object($data, 'fundings') ?? []),
            peerReviews: PeerReviewGroups::fromArray(Json::object($data, 'peer-reviews') ?? []),
            researchResources: ResearchResourceGroups::fromArray(Json::object($data, 'research-resources') ?? []),
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

    public function workCount(): int
    {
        return count($this->works->groups);
    }

    public function fundingCount(): int
    {
        return count($this->fundings->groups);
    }

    /**
     * The number of review groups (e.g. journals), ORCID's top-level groups of peer reviews; count
     * peerReviews->groups for the number of distinct peer reviews
     */
    public function peerReviewCount(): int
    {
        return count($this->peerReviews->byReviewGroup());
    }

    public function researchResourceCount(): int
    {
        return count($this->researchResources->groups);
    }
}
