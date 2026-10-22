<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Exceptions;

/**
 * The token given to the connector does not have a scope the request needs.
 * Thrown before anything is sent, only when the connector was told the token scopes.
 */
final class MissingScopeException extends OrcidException
{
    //
}
