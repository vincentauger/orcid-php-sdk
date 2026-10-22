<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Support\Json;

final readonly class PersonName
{
    public function __construct(
        public ?string $givenNames = null,
        public ?string $familyName = null,
        /** The name the person prefers to be credited with ("published name") */
        public ?string $creditName = null,
        public ?string $visibility = null,
    ) {}

    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(
            givenNames: Json::string($data, 'given-names'),
            familyName: Json::string($data, 'family-name'),
            creditName: Json::string($data, 'credit-name'),
            visibility: Json::string($data, 'visibility'),
        );
    }

    /**
     * Credit name if set, otherwise "given-names family-name"
     */
    public function displayName(): ?string
    {
        if ($this->creditName !== null) {
            return $this->creditName;
        }

        $name = trim(($this->givenNames ?? '').' '.($this->familyName ?? ''));

        return $name === '' ? null : $name;
    }
}
