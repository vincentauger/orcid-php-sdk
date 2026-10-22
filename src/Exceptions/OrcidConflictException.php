<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Exceptions;

/**
 * 409: the item conflicts with an existing one (e.g. a duplicate external identifier).
 */
final class OrcidConflictException extends OrcidRequestException
{
    //
}
