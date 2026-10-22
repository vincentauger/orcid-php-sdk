<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * An email on a record. Only emails the person made visible to you are returned.
 */
final readonly class Email
{
    public function __construct(
        public string $email,
        public bool $primary = false,
        public bool $verified = false,
        public ?string $visibility = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $email = Json::string($data, 'email');

        if ($email === null) {
            return null;
        }

        return new self(
            email: $email,
            primary: ($data['primary'] ?? false) === true,
            verified: ($data['verified'] ?? false) === true,
            visibility: Json::string($data, 'visibility'),
        );
    }
}
