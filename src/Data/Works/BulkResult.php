<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Works;

use VincentAuger\OrcidSdk\Data\OrcidError;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * The outcome of createMany(): each work was either created or refused on its own, so both are keyed by the
 * position of its input. A duplicate is refused with error 9021 (`isDuplicate()`, `existingPutCode()`).
 */
final readonly class BulkResult
{
    /**
     * @param  array<int, int>  $created  Input position => put-code of the new work
     * @param  array<int, OrcidError>  $failed  Input position => why ORCID refused the work
     */
    public function __construct(
        public array $created = [],
        public array $failed = [],
    ) {}

    /**
     * Read a bulk create response ({"bulk": [{"work": {...}} or {"error": {...}}, ...]}, in input order)
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $created = [];
        $failed = [];

        foreach (Json::list($data, 'bulk') as $position => $item) {
            $work = Json::object($item, 'work');
            $putCode = $work === null ? null : Json::int($work, 'put-code');

            if ($putCode !== null) {
                $created[$position] = $putCode;
            } else {
                $failed[$position] = OrcidError::fromArray(Json::object($item, 'error') ?? []);
            }
        }

        return new self($created, $failed);
    }

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }
}
