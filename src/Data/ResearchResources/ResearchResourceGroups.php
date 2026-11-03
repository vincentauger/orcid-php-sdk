<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\ResearchResources;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * The research resources of a record, grouped by ORCID: the same proposal from several sources is one group
 */
final readonly class ResearchResourceGroups
{
    /**
     * @param  list<ResearchResourceGroup>  $groups
     */
    public function __construct(
        public array $groups = [],
        public ?\DateTimeImmutable $lastModifiedAt = null,
    ) {}

    /**
     * Read a list response ({"group": [{"external-ids": ..., "research-resource-summary": [...]}]})
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            groups: array_values(array_filter(array_map(ResearchResourceGroup::fromArray(...), Json::list($data, 'group')))),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
        );
    }

    /**
     * One summary per group, the one ORCID displays: to show a researcher's research resources
     *
     * @return list<ResearchResourceSummary>
     */
    public function preferred(): array
    {
        return array_map(static fn (ResearchResourceGroup $group): ResearchResourceSummary => $group->preferred(), $this->groups);
    }

    /**
     * Every summary of every group: to sync, since your own research resource may not be the preferred one
     *
     * @return list<ResearchResourceSummary>
     */
    public function summaries(): array
    {
        return array_merge(...array_map(static fn (ResearchResourceGroup $group): array => $group->summaries, $this->groups));
    }
}
