<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Exceptions;

use VincentAuger\OrcidSdk\Data\OrcidError;

/**
 * 409: the item conflicts with an existing one. Check `errorCode`:
 *
 * - 9021: this client already added an item with the same `self` external identifier (on create, or on an
 *   update that would give the item another one's identifier). The item is "already there": `existingPutCode()`
 * - 9030: this client already added a person item (e.g. a keyword) with the same content, on create or update.
 *   ORCID does not name the existing item: `existingPutCode()` is null
 */
final class OrcidConflictException extends OrcidRequestException
{
    public const int DUPLICATE = OrcidError::DUPLICATE;

    public const int DUPLICATE_CONTENT = OrcidError::DUPLICATE_CONTENT;

    /**
     * Whether this client already added the item (9021 or 9030)
     */
    public function isDuplicate(): bool
    {
        return new OrcidError(errorCode: $this->errorCode)->isDuplicate();
    }

    /**
     * The put-code of the existing item, for a duplicate. ORCID only gives it in the developer message
     * ("... please see element with put-code 2556381 ..."), so this is null if ORCID changes that text, and
     * for a duplicate person item (9030).
     */
    public function existingPutCode(): ?int
    {
        return new OrcidError(errorCode: $this->errorCode, developerMessage: $this->developerMessage)->existingPutCode();
    }
}
