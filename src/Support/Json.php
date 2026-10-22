<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Support;

/**
 * Helpers to read ORCID JSON, where most scalars are wrapped as {"value": ...}.
 *
 * @internal
 */
final class Json
{
    /**
     * A string at `$key`, unwrapping {"value": ...}; null when missing or blank
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function string(array $data, string $key): ?string
    {
        $value = self::unwrap($data[$key] ?? null);

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function int(array $data, string $key): ?int
    {
        $value = self::unwrap($data[$key] ?? null);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * A nested object at `$key`, or null when missing
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>|null
     */
    public static function object(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * A list of objects at `$key` (empty when missing)
     *
     * @param  array<array-key, mixed>  $data
     * @return list<array<array-key, mixed>>
     */
    public static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_array(...)));
    }

    /**
     * An ORCID timestamp ({"value": <milliseconds since epoch>}) at `$key`
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function timestamp(array $data, string $key): ?\DateTimeImmutable
    {
        $milliseconds = self::int($data, $key);

        if ($milliseconds === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('U.u', sprintf('%d.%03d000', intdiv($milliseconds, 1000), $milliseconds % 1000));

        return $date === false ? null : $date;
    }

    /**
     * Wrap a value as ORCID expects ({"value": ...}), keeping null as null
     *
     * @return array{value: string}|null
     */
    public static function wrap(?string $value): ?array
    {
        return $value === null ? null : ['value' => $value];
    }

    /**
     * Remove null entries, since ORCID treats null and missing the same way
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withoutNulls(array $data): array
    {
        return array_filter($data, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Trim a string, turning blank values into null (ORCID rejects blank text)
     */
    public static function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function unwrap(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value;
    }
}
