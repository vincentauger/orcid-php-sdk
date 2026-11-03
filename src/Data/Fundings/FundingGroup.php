<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Fundings;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Fundings from different sources that ORCID considers the same, because they share a `self` external identifier.
 * A funding without one (only `part-of` or `funded-by` ids) is a group of its own, with no external ids (`c2/f38`).
 */
final readonly class FundingGroup
{
    /**
     * @param  list<ExternalId>  $externalIds  The group's `self` identifiers
     * @param  non-empty-list<FundingSummary>  $summaries  In ORCID's order
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
        $summaries = array_map(FundingSummary::fromArray(...), Json::list($data, 'funding-summary'));

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
     * The summary ORCID displays: the highest display index (the one the researcher chose, else a client's funding
     * over the researcher's own), the first one listed on a tie
     */
    public function preferred(): FundingSummary
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
