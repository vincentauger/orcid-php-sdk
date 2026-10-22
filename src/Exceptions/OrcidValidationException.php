<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Exceptions;

/**
 * 400: ORCID rejected the payload (schema errors, missing required fields, ...).
 * Sending the same data again will fail again.
 */
final class OrcidValidationException extends OrcidRequestException
{
    //
}
