<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Exceptions;

/**
 * 5xx: ORCID is down or failing. Retry later.
 */
final class OrcidServerException extends OrcidRequestException
{
    public function isRetryable(): bool
    {
        return true;
    }
}
