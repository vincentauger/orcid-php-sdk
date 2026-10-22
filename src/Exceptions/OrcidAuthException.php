<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Exceptions;

/**
 * 401: the token is invalid, expired or was revoked by the record owner
 * (OAuth error `invalid_token`). The record owner has to authorize again.
 */
final class OrcidAuthException extends OrcidRequestException
{
    //
}
