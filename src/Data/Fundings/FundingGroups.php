<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Fundings;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * The fundings of a record, grouped by ORCID: the same funding from several sources is one group
 */
final readonly class FundingGroups
{
    /**
     * @param  list<FundingGroup>  $groups
     */
    public function __construct(
        public array $groups = [],
        public ?\DateTimeImmutable $lastModifiedAt = null,
    ) {}

    /**
     * Read a list response ({"group": [{"external-ids": ..., "funding-summary": [...]}]})
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            groups: array_values(array_filter(array_map(FundingGroup::fromArray(...), Json::list($data, 'group')))),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
        );
    }

    /**
     * One summary per group, the one ORCID displays: to show a researcher's fundings
     *
     * @return list<FundingSummary>
     */
    public function preferred(): array
    {
        return array_map(static fn (FundingGroup $group): FundingSummary => $group->preferred(), $this->groups);
    }

    /**
     * Every summary of every group: to sync, since your own funding may not be the preferred one
     *
     * @return list<FundingSummary>
     */
    public function summaries(): array
    {
        return array_merge(...array_map(static fn (FundingGroup $group): array => $group->summaries, $this->groups));
    }
}
