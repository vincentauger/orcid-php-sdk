<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Exceptions;

/**
 * ORCID could not be reached (DNS, connection refused, timeout, ...). Retry later.
 */
final class OrcidConnectionException extends OrcidException
{
    public function isRetryable(): bool
    {
        return true;
    }
}
