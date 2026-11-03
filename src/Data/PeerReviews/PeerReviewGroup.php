<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\PeerReviews;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Peer reviews from different sources that ORCID considers the same, because they are in the same review group and
 * share a `self` review identifier. A peer review without one (only `part-of` ids) is a group of its own, with no
 * external ids (`c3/r35`).
 */
final readonly class PeerReviewGroup
{
    /**
     * @param  string  $reviewGroupId  The review group holding this group, e.g. "issn:0706-652X"
     * @param  list<ExternalId>  $externalIds  The group's `self` review identifiers
     * @param  non-empty-list<PeerReviewSummary>  $summaries  In ORCID's order
     */
    public function __construct(
        public string $reviewGroupId,
        public array $externalIds,
        public array $summaries,
        public ?\DateTimeImmutable $lastModifiedAt = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  One element of a review group's "peer-review-group" list
     */
    public static function fromArray(string $reviewGroupId, array $data): ?self
    {
        $summaries = array_map(PeerReviewSummary::fromArray(...), Json::list($data, 'peer-review-summary'));

        if ($summaries === []) {
            return null;
        }

        return new self(
            reviewGroupId: $reviewGroupId,
            externalIds: ExternalId::listFromArray(Json::object($data, 'external-ids')),
            summaries: $summaries,
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
        );
    }

    /**
     * The summary ORCID displays: the highest display index (the one the researcher chose, else a client's peer
     * review over the researcher's own), the first one listed on a tie
     */
    public function preferred(): PeerReviewSummary
    {
        $preferred = $this->summaries[0];

        foreach ($this->summaries as $summary) {
            if (($summary->displayIndex ?? 0) > ($preferred->displayIndex ?? 0)) {
                $preferred = $summary;
            }
        }

        return $preferred;
    }
}
