<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * Record API version, used as the first path segment of record requests.
 * Webhook endpoints are not versioned.
 */
enum ApiVersion: string
{
    case V3_0 = 'v3.0';
}
