<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\ResearchResources;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * Data to create or update a research resource: a proposal and the resource items it gave access to.
 *
 * ORCID replaces the whole item on update, so every field you want to keep must be sent again. Blank URLs are
 * refused by Url; ORCID would drop them (`c4/s27`).
 *
 * At least one resource item is required (error 9047, `c4/s08`, `s32`). Sending a `self` proposal identifier this
 * client already used fails (409, error 9021, `c4/s20`). ORCID groups a record's research resources by the proposal's
 * `self` identifiers.
 */
final readonly class ResearchResourceInput
{
    /**
     * @param  list<ResearchResourceItemInput>  $items
     */
    public function __construct(
        public ResearchResourceProposalInput $proposal,
        public array $items,
    ) {
        if ($items === []) {
            throw new \InvalidArgumentException('ORCID requires a resource item (error 9047)');
        }
    }

    /**
     * ORCID JSON body. For an update, pass the item's put-code.
     *
     * @return array<string, mixed>
     */
    public function toArray(?int $putCode = null): array
    {
        return Json::withoutNulls([
            'put-code' => $putCode,
            'proposal' => $this->proposal->toArray(),
            'resource-item' => array_map(static fn (ResearchResourceItemInput $item): array => $item->toArray(), $this->items),
        ]);
    }
}
