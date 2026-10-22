<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Common;

use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * An identifier attached to an item (grant number, DOI, internal id, ...)
 */
final readonly class ExternalId
{
    public function __construct(
        /** ORCID identifier type, e.g. "doi", "grant_number", "proposal-id" */
        public string $type,
        public string $value,
        public ExternalIdRelationship $relationship = ExternalIdRelationship::Self,
        public ?string $url = null,
    ) {
        if (trim($type) === '' || trim($value) === '') {
            throw new \InvalidArgumentException('An external id needs a type and a value');
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $type = Json::string($data, 'external-id-type');
        $value = Json::string($data, 'external-id-value');

        if ($type === null || $value === null) {
            return null;
        }

        return new self(
            type: $type,
            value: $value,
            relationship: ExternalIdRelationship::tryFrom(Json::string($data, 'external-id-relationship') ?? '') ?? ExternalIdRelationship::Self,
            url: Json::string($data, 'external-id-url'),
        );
    }

    /**
     * Read the {"external-id": [...]} wrapper used by ORCID
     *
     * @param  array<array-key, mixed>|null  $data
     * @return list<self>
     */
    public static function listFromArray(?array $data): array
    {
        if ($data === null) {
            return [];
        }

        return array_values(array_filter(array_map(self::fromArray(...), Json::list($data, 'external-id'))));
    }

    /**
     * @param  list<self>  $externalIds
     * @return array{external-id: list<array<string, mixed>>}|null
     */
    public static function listToArray(array $externalIds): ?array
    {
        if ($externalIds === []) {
            return null;
        }

        return ['external-id' => array_map(static fn (self $id): array => $id->toArray(), $externalIds)];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::withoutNulls([
            'external-id-type' => $this->type,
            'external-id-value' => $this->value,
            'external-id-url' => Json::wrap($this->url),
            'external-id-relationship' => $this->relationship->value,
        ]);
    }
}
