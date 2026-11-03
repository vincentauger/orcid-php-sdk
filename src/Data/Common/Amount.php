<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Common;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * An amount of money: a decimal number as a string (no thousands separator, a dot for decimals) and an ISO 4217
 * currency code. ORCID requires both (errors 9020 and 9047, `c2/f20`, `f24`), refuses other number formats
 * (9054, `f23`) and stores the value normalized ("125000.50" is read back as "125000.5", `f15`).
 *
 * The currency is uppercased (ORCID refuses "cad", `f34`) and checked to be three letters; ORCID alone knows the
 * ISO 4217 list, so an unknown code such as "XYZ" is still refused there (9047, `f21`).
 */
final readonly class Amount implements \Stringable
{
    public string $value;

    /**
     * ISO 4217, uppercase, e.g. "CAD"
     */
    public string $currency;

    /**
     * @param  string  $value  e.g. "125000.50", kept as a string so no precision is lost
     */
    public function __construct(string $value, string $currency)
    {
        $value = trim($value);
        $currency = strtoupper(trim($currency));

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid amount "%s", expected a number such as "125000.50" (error 9054)', $value));
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid currency "%s", expected an ISO 4217 code such as "CAD"', $currency));
        }

        $this->value = $value;
        $this->currency = $currency;
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        $value = $data === null ? null : Json::string($data, 'value');
        $currency = $data === null ? null : Json::string($data, 'currency-code');

        if ($value === null || $currency === null) {
            return null;
        }

        try {
            return new self($value, $currency);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return array{value: string, currency-code: string}
     */
    public function toArray(): array
    {
        return ['value' => $this->value, 'currency-code' => $this->currency];
    }

    public function __toString(): string
    {
        return $this->value.' '.$this->currency;
    }
}
