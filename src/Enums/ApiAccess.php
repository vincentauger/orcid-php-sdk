<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * ORCID exposes two record APIs:
 *
 * - Public API (pub.orcid.org): read-only, public data. Any client can get a /read-public token,
 *   including self-service Public API clients from the ORCID Developer Tools page.
 * - Member API (api.orcid.org): read limited data and write to records. Requires member API
 *   credentials issued by ORCID; webhooks also require a premium membership.
 */
enum ApiAccess: string
{
    case Public = 'public';
    case Member = 'member';
}
