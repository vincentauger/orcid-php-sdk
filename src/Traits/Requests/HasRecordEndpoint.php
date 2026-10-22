<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Traits\Requests;

use VincentAuger\OrcidSdk\Enums\ApiVersion;

/**
 * Record requests live under /v3.0/{orcid}/... (the connector base URL is the host only)
 */
trait HasRecordEndpoint
{
    protected function recordEndpoint(string $orcid, string $path = ''): string
    {
        return rtrim('/'.ApiVersion::V3_0->value.'/'.$orcid.'/'.ltrim($path, '/'), '/');
    }
}
