<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\ResearchResources;

use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * A research resource as returned by ORCID, read in full by put-code: a proposal and the resource items it gave
 * access to. Use ResearchResourceInput to create or update one.
 */
final readonly class ResearchResource
{
    /**
     * @param  list<ResearchResourceItem>  $items
     */
    public function __construct(
        public int $putCode,
        public ?ResearchResourceProposal $proposal,
        public array $items = [],
        public ?Source $source = null,
        public ?Visibility $visibility = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
        public ?int $displayIndex = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID research resource has no put-code'),
            proposal: ResearchResourceProposal::fromArray(Json::object($data, 'proposal')),
            items: array_map(ResearchResourceItem::fromArray(...), Json::list($data, 'resource-item')),
            source: Source::fromArray(Json::object($data, 'source')),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
            displayIndex: Json::int($data, 'display-index'),
        );
    }
}
