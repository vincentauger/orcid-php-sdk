<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Orcid;

/**
 * Calls scoped to one ORCID record: $orcid->record('0000-0002-1825-0097')->...
 */
final readonly class RecordResource
{
    public function __construct(
        private Orcid $connector,
        /** The normalised ORCID iD */
        public string $orcid,
    ) {}

    public function affiliations(): AffiliationsResource
    {
        return new AffiliationsResource($this->connector, $this->orcid);
    }
}
