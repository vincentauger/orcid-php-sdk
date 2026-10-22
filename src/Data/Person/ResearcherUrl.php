<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * A website listed on a record (personal site, lab page, profile, ...)
 */
final readonly class ResearcherUrl
{
    public function __construct(
        public string $url,
        public ?string $name = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $url = Json::string($data, 'url');

        return $url === null ? null : new self($url, Json::string($data, 'url-name'));
    }
}
