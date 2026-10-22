<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Contracts;

use VincentAuger\OrcidSdk\Enums\Scope;

/**
 * Requests that need specific token scopes. When the Orcid connector knows its
 * token's scopes, it throws MissingScopeException before sending the request.
 */
interface RequiresScopes
{
    /**
     * @return Scope[]
     */
    public function requiredScopes(): array;
}
