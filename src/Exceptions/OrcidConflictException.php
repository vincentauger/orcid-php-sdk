<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Exceptions;

/**
 * 409: the item conflicts with an existing one. Check `errorCode`:
 *
 * - 9021: this client already added an item with the same `self` external identifier (on create, or on an
 *   update that would give the item another one's identifier). The item is "already there": `existingPutCode()`
 */
final class OrcidConflictException extends OrcidRequestException
{
    public const int DUPLICATE = 9021;

    public function isDuplicate(): bool
    {
        return $this->errorCode === self::DUPLICATE;
    }

    /**
     * The put-code of the existing item, for a duplicate. ORCID only gives it in the developer message
     * ("... please see element with put-code 2556381 ..."), so this is null if ORCID changes that text.
     */
    public function existingPutCode(): ?int
    {
        if (! $this->isDuplicate() || preg_match('/put-code (\d+)/', $this->developerMessage ?? '', $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
