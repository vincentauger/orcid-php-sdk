<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * A website listed on a record (personal site, lab page, profile, ...)
 */
final readonly class ResearcherUrl
{
    public function __construct(
        public string $url,
        public int $putCode,
        public ?string $name = null,
        public ?Visibility $visibility = null,
        public ?Source $source = null,
        /** The order the researcher chose among their websites */
        public ?int $displayIndex = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     * @return self|null null without a URL
     */
    public static function fromArray(array $data): ?self
    {
        $url = Json::string($data, 'url');

        if ($url === null) {
            return null;
        }

        return new self(
            url: $url,
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID researcher URL has no put-code'),
            name: Json::string($data, 'url-name'),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            source: Source::fromArray(Json::object($data, 'source')),
            displayIndex: Json::int($data, 'display-index'),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
        );
    }

    /**
     * Read the {"researcher-url": [...]} list (the person section's "researcher-urls")
     *
     * @param  array<array-key, mixed>|null  $data
     * @return list<self>
     */
    public static function listFromArray(?array $data): array
    {
        return array_values(array_filter(array_map(self::fromArray(...), Json::list($data ?? [], 'researcher-url'))));
    }
}
