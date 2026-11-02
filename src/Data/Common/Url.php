<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Common;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * The URL of an item, sent as {"value": ...}. ORCID stores any non-blank string here (`c1/q07`), so read
 * values may not be valid URLs.
 */
final readonly class Url implements \Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = Json::blankToNull($value) ?? throw new \InvalidArgumentException('A URL cannot be blank');
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        $value = $data === null ? null : Json::string($data, 'value');

        return $value === null ? null : new self($value);
    }

    /**
     * @return array{value: string}
     */
    public function toArray(): array
    {
        return ['value' => $this->value];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
