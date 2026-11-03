<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * The researcher's biography. Only the researcher can change it: it has no put-code or source.
 */
final readonly class Biography
{
    public function __construct(
        public string $content,
        public ?Visibility $visibility = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
    ) {}

    /**
     * @param  array<array-key, mixed>|null  $data
     * @return self|null null for a missing or blank biography
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        $content = Json::string($data, 'content');

        if ($content === null) {
            return null;
        }

        return new self(
            content: $content,
            visibility: Json::enum($data, 'visibility', Visibility::class),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
        );
    }
}
