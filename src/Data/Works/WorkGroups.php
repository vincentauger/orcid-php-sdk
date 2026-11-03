<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Works;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * The works of a record, grouped by ORCID: the same work from several sources is one group
 */
final readonly class WorkGroups
{
    /**
     * @param  list<WorkGroup>  $groups
     */
    public function __construct(
        public array $groups = [],
        public ?\DateTimeImmutable $lastModifiedAt = null,
    ) {}

    /**
     * Read a list response ({"group": [{"external-ids": ..., "work-summary": [...]}]})
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            groups: array_values(array_filter(array_map(WorkGroup::fromArray(...), Json::list($data, 'group')))),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
        );
    }

    /**
     * One summary per group, the one ORCID displays: to show a researcher's works
     *
     * @return list<WorkSummary>
     */
    public function preferred(): array
    {
        return array_map(static fn (WorkGroup $group): WorkSummary => $group->preferred(), $this->groups);
    }

    /**
     * Every summary of every group: to sync, since your own work may not be the preferred one
     *
     * @return list<WorkSummary>
     */
    public function summaries(): array
    {
        return array_merge(...array_map(static fn (WorkGroup $group): array => $group->summaries, $this->groups));
    }
}
