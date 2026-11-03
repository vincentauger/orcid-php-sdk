<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Works;

use VincentAuger\OrcidSdk\Data\OrcidError;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * The outcome of getMany(), keyed by put-code: a put-code ORCID doesn't know on the record is a failure
 * (error 9034), not a 404, and the other works are still read.
 */
final readonly class BulkRead
{
    /**
     * @param  array<int, Work>  $works  Put-code => the work, in the order asked
     * @param  array<int, OrcidError>  $failed  Put-code => why ORCID could not read it
     */
    public function __construct(
        public array $works = [],
        public array $failed = [],
    ) {}

    /**
     * Read a bulk read response ({"bulk": [{"work": {...}} or {"error": {...}}, ...]}, in the order of the put-codes)
     *
     * @param  array<array-key, mixed>  $data
     * @param  list<int>  $putCodes  The put-codes sent, in order
     */
    public static function fromArray(array $data, array $putCodes): self
    {
        $works = [];
        $failed = [];

        foreach (Json::list($data, 'bulk') as $position => $item) {
            $work = Json::object($item, 'work');

            if ($work !== null) {
                $work = Work::fromArray($work);
                $works[$work->putCode] = $work;
            } elseif (isset($putCodes[$position])) {
                $failed[$putCodes[$position]] = OrcidError::fromArray(Json::object($item, 'error') ?? []);
            }
        }

        return new self($works, $failed);
    }

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }
}
