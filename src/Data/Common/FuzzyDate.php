<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Common;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * ORCID "fuzzy" date: a year, optionally with a month, optionally with a day.
 */
final readonly class FuzzyDate implements \Stringable
{
    public function __construct(
        public int $year,
        public ?int $month = null,
        public ?int $day = null,
    ) {
        if ($year < 1000 || $year > 9999) {
            throw new \InvalidArgumentException(sprintf('Invalid year: %d', $year));
        }

        if ($month !== null && ($month < 1 || $month > 12)) {
            throw new \InvalidArgumentException(sprintf('Invalid month: %d', $month));
        }

        if ($day !== null && $month === null) {
            throw new \InvalidArgumentException('A day requires a month');
        }

        if ($day !== null && ! checkdate($month, $day, $year)) {
            throw new \InvalidArgumentException(sprintf('Invalid date: %d-%d-%d', $year, $month, $day));
        }
    }

    /**
     * From "2021", "2021-01" or "2021-01-05"
     */
    public static function fromString(string $date): self
    {
        if (preg_match('/^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/', trim($date), $m) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid date "%s", expected YYYY, YYYY-MM or YYYY-MM-DD', $date));
        }

        return new self(
            (int) $m[1],
            isset($m[2]) ? (int) $m[2] : null,
            isset($m[3]) ? (int) $m[3] : null,
        );
    }

    public static function fromDateTime(\DateTimeInterface $date): self
    {
        return new self((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
    }

    /**
     * From ORCID JSON. Accepts {"year": {"value": "2021"}} and plain {"year": "2021"}.
     *
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        $year = $data === null ? null : Json::int($data, 'year');

        if ($data === null || $year === null) {
            return null;
        }

        return new self($year, Json::int($data, 'month'), Json::int($data, 'day'));
    }

    /**
     * Whether this date is before another, compared only to the precision both
     * have: 2021 is not before 2021-05, but 2021-04 is.
     */
    public function isBefore(self $other): bool
    {
        if ($this->year !== $other->year) {
            return $this->year < $other->year;
        }

        if ($this->month === null || $other->month === null || $this->month !== $other->month) {
            return $this->month !== null && $other->month !== null && $this->month < $other->month;
        }

        return $this->day !== null && $other->day !== null && $this->day < $other->day;
    }

    /**
     * @return array<string, array{value: string}>
     */
    public function toArray(): array
    {
        return array_filter([
            'year' => ['value' => sprintf('%04d', $this->year)],
            'month' => $this->month === null ? null : ['value' => sprintf('%02d', $this->month)],
            'day' => $this->day === null ? null : ['value' => sprintf('%02d', $this->day)],
        ]);
    }

    public function __toString(): string
    {
        return implode('-', array_map(
            static fn (array $part): string => $part['value'],
            $this->toArray(),
        ));
    }
}
