<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\ResearchResources;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Research resources from different sources that ORCID considers the same, because their proposals share a `self`
 * external identifier.
 * A research resource without one (only `part-of` ids) is a group of its own, with no external ids (`c4/s38`).
 */
final readonly class ResearchResourceGroup
{
    /**
     * @param  list<ExternalId>  $externalIds  The group's `self` proposal identifiers
     * @param  non-empty-list<ResearchResourceSummary>  $summaries  In ORCID's order
     */
    public function __construct(
        public array $externalIds,
        public array $summaries,
        public ?\DateTimeImmutable $lastModifiedAt = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $summaries = array_map(ResearchResourceSummary::fromArray(...), Json::list($data, 'research-resource-summary'));

        if ($summaries === []) {
            return null;
        }

        return new self(
            externalIds: ExternalId::listFromArray(Json::object($data, 'external-ids')),
            summaries: $summaries,
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
        );
    }

    /**
     * The summary ORCID displays: the highest display index (the one the researcher chose, else a client's research
     * resource over the researcher's own), the first one listed on a tie
     */
    public function preferred(): ResearchResourceSummary
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
