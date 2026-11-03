<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Another name the researcher is known by (a maiden name, a transliteration, ...)
 */
final readonly class OtherName
{
    public function __construct(
        public string $content,
        public int $putCode,
        public ?Visibility $visibility = null,
        public ?Source $source = null,
        /** The order the researcher chose among their other names */
        public ?int $displayIndex = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     * @return self|null null for a blank name
     */
    public static function fromArray(array $data): ?self
    {
        $content = Json::string($data, 'content');

        if ($content === null) {
            return null;
        }

        return new self(
            content: $content,
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID other name has no put-code'),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            source: Source::fromArray(Json::object($data, 'source')),
            displayIndex: Json::int($data, 'display-index'),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
        );
    }

    /**
     * Read the {"other-name": [...]} list (the person section's "other-names")
     *
     * @param  array<array-key, mixed>|null  $data
     * @return list<self>
     */
    public static function listFromArray(?array $data): array
    {
        return array_values(array_filter(array_map(self::fromArray(...), Json::list($data ?? [], 'other-name'))));
    }
}
