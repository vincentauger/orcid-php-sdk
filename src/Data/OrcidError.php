<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * An error ORCID returned for one item of a bulk call, while the request itself succeeded. Same fields as an
 * OrcidRequestException, which is thrown instead when the whole request fails.
 */
final readonly class OrcidError
{
    /**
     * This client already added an item with the same `self` external identifier
     */
    public const int DUPLICATE = 9021;

    /**
     * This client already added a person item (e.g. a keyword) with the same content. ORCID does not name it.
     */
    public const int DUPLICATE_CONTENT = 9030;

    /**
     * @param  int|null  $responseCode  The HTTP status ORCID would have answered for this item alone (e.g. 409)
     */
    public function __construct(
        public ?int $responseCode = null,
        public ?int $errorCode = null,
        public ?string $developerMessage = null,
        public ?string $userMessage = null,
        public ?string $moreInfo = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            responseCode: Json::int($data, 'response-code'),
            errorCode: Json::int($data, 'error-code'),
            developerMessage: Json::string($data, 'developer-message'),
            userMessage: Json::string($data, 'user-message'),
            moreInfo: Json::string($data, 'more-info'),
        );
    }

    /**
     * Whether this client already added the item: an activity (e.g. a work) with the same `self` external
     * identifier (error 9021), or a person item (e.g. a keyword) with the same content (9030)
     */
    public function isDuplicate(): bool
    {
        return $this->errorCode === self::DUPLICATE || $this->errorCode === self::DUPLICATE_CONTENT;
    }

    /**
     * The put-code of the existing item, for a duplicate. ORCID only gives it in the developer message
     * ("... please see element with put-code 2556381 ..."), so this is null if ORCID changes that text, and
     * for a duplicate person item (9030), whose message has no put-code.
     */
    public function existingPutCode(): ?int
    {
        if ($this->errorCode !== self::DUPLICATE || preg_match('/put-code (\d+)/', $this->developerMessage ?? '', $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
