<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Works;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Works from different sources that ORCID considers the same, because they share a `self` external identifier
 */
final readonly class WorkGroup
{
    /**
     * @param  list<ExternalId>  $externalIds  The group's `self` identifiers, with ORCID's normalized values
     * @param  non-empty-list<WorkSummary>  $summaries  In ORCID's order
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
        $summaries = array_map(WorkSummary::fromArray(...), Json::list($data, 'work-summary'));

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
     * The summary ORCID displays: the highest display index (a new work gets the highest one), the first one listed
     * on a tie
     */
    public function preferred(): WorkSummary
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
