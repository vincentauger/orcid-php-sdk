<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\PeerReviews;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * The peer reviews of a record, grouped by ORCID: the same peer review from several sources is one group. ORCID
 * nests the groups in their review group (e.g. a journal); here they are one list, each group knowing its review
 * group, and byReviewGroup() nests them again.
 */
final readonly class PeerReviewGroups
{
    /**
     * @param  list<PeerReviewGroup>  $groups  In ORCID's order
     */
    public function __construct(
        public array $groups = [],
        public ?\DateTimeImmutable $lastModifiedAt = null,
    ) {}

    /**
     * Read a list response ({"group": [{"external-ids": <review group id>, "peer-review-group": [{"external-ids",
     * "peer-review-summary": [...]}]}]})
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $groups = [];

        foreach (Json::list($data, 'group') as $reviewGroup) {
            $reviewGroupId = ExternalId::listFromArray(Json::object($reviewGroup, 'external-ids'))[0]->value ?? null;

            foreach (Json::list($reviewGroup, 'peer-review-group') as $group) {
                // each summary repeats its review group id, should the review group lack one
                $summaries = Json::list($group, 'peer-review-summary');
                $id = $reviewGroupId ?? (isset($summaries[0]) ? Json::string($summaries[0], 'review-group-id') : null) ?? '';

                $groups[] = PeerReviewGroup::fromArray($id, $group);
            }
        }

        return new self(
            groups: array_values(array_filter($groups)),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
        );
    }

    /**
     * The groups by review group id, e.g. to show a researcher's peer reviews journal by journal
     *
     * @return array<string, non-empty-list<PeerReviewGroup>>
     */
    public function byReviewGroup(): array
    {
        $byReviewGroup = [];

        foreach ($this->groups as $group) {
            $byReviewGroup[$group->reviewGroupId][] = $group;
        }

        return $byReviewGroup;
    }

    /**
     * One summary per group, the one ORCID displays: to show a researcher's peer reviews
     *
     * @return list<PeerReviewSummary>
     */
    public function preferred(): array
    {
        return array_map(static fn (PeerReviewGroup $group): PeerReviewSummary => $group->preferred(), $this->groups);
    }

    /**
     * Every summary of every group: to sync, since your own peer review may not be the preferred one
     *
     * @return list<PeerReviewSummary>
     */
    public function summaries(): array
    {
        return array_merge(...array_map(static fn (PeerReviewGroup $group): array => $group->summaries, $this->groups));
    }
}
