<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * A keyword on a record, as returned by person() and keywords(). Use KeywordInput to create or update one.
 */
final readonly class Keyword
{
    public function __construct(
        public string $content,
        public int $putCode,
        public ?Visibility $visibility = null,
        public ?Source $source = null,
        /** The order the researcher chose among their keywords */
        public ?int $displayIndex = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     * @return self|null null for a blank keyword
     */
    public static function fromArray(array $data): ?self
    {
        $content = Json::string($data, 'content');

        if ($content === null) {
            return null;
        }

        return new self(
            content: $content,
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID keyword has no put-code'),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            source: Source::fromArray(Json::object($data, 'source')),
            displayIndex: Json::int($data, 'display-index'),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
        );
    }

    /**
     * Read the {"keyword": [...]} list (GET /keywords, and the person section's "keywords")
     *
     * @param  array<array-key, mixed>|null  $data
     * @return list<self>
     */
    public static function listFromArray(?array $data): array
    {
        return array_values(array_filter(array_map(self::fromArray(...), Json::list($data ?? [], 'keyword'))));
    }
}
