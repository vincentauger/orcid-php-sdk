<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Exceptions;

/**
 * 429: too many requests. Retry later.
 */
final class OrcidRateLimitException extends OrcidRequestException
{
    public function isRetryable(): bool
    {
        return true;
    }
}
