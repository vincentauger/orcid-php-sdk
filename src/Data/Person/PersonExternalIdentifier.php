<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * The researcher's identifier in another system (Scopus Author ID, ResearcherID, ...)
 */
final readonly class PersonExternalIdentifier
{
    public function __construct(
        public ExternalId $externalId,
        public int $putCode,
        public ?Visibility $visibility = null,
        public ?Source $source = null,
        /** The order the researcher chose among their external identifiers */
        public ?int $displayIndex = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     * @return self|null null without a type or a value
     */
    public static function fromArray(array $data): ?self
    {
        $externalId = ExternalId::fromArray($data);

        if (! $externalId instanceof ExternalId) {
            return null;
        }

        return new self(
            externalId: $externalId,
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID external identifier has no put-code'),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            source: Source::fromArray(Json::object($data, 'source')),
            displayIndex: Json::int($data, 'display-index'),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
        );
    }

    /**
     * Read the {"external-identifier": [...]} list (the person section's "external-identifiers")
     *
     * @param  array<array-key, mixed>|null  $data
     * @return list<self>
     */
    public static function listFromArray(?array $data): array
    {
        return array_values(array_filter(array_map(self::fromArray(...), Json::list($data ?? [], 'external-identifier'))));
    }
}
