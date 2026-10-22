<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Exceptions;

/**
 * Base exception for every error thrown by the SDK.
 */
class OrcidException extends \RuntimeException
{
    /**
     * Whether retrying the same call later may succeed (rate limits, server and
     * connection errors). Anything else will fail the same way again.
     */
    public function isRetryable(): bool
    {
        return false;
    }
}
