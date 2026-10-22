<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Exceptions;

/**
 * 403: the token is valid but not allowed to do this. Check `errorCode`:
 *
 * - 9010: the client is not the source of the item (only the client that created an item can change it)
 * - 9038: the token does not have the scope this call needs
 */
final class OrcidForbiddenException extends OrcidRequestException
{
    public const int NOT_SOURCE = 9010;

    public const int MISSING_SCOPE = 9038;

    public function isNotSource(): bool
    {
        return $this->errorCode === self::NOT_SOURCE;
    }

    public function isMissingScope(): bool
    {
        return $this->errorCode === self::MISSING_SCOPE;
    }
}
