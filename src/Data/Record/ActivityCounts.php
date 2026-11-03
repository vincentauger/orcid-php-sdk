<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Record;

/**
 * How many items of an activity section a record summary counts, by who added them
 */
final readonly class ActivityCounts
{
    public function __construct(
        /** Added by a client (an ORCID integration): the item's source vouches for it */
        public int $validated = 0,
        /** Added by the researcher themselves */
        public int $selfAsserted = 0,
    ) {}

    public function total(): int
    {
        return $this->validated + $this->selfAsserted;
    }
}
