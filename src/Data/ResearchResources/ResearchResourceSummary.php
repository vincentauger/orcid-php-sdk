<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\ResearchResources;

use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * A research resource as listed in a group: its proposal only, without resource items (`c4/s38`). Read the full
 * research resource by put-code.
 */
final readonly class ResearchResourceSummary
{
    public function __construct(
        public int $putCode,
        public ?ResearchResourceProposal $proposal,
        public ?Source $source = null,
        public ?Visibility $visibility = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
        /** The highest display index in a group marks the preferred summary (ResearchResourceGroup::preferred()) */
        public ?int $displayIndex = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID research resource summary has no put-code'),
            proposal: ResearchResourceProposal::fromArray(Json::object($data, 'proposal')),
            source: Source::fromArray(Json::object($data, 'source')),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
            displayIndex: Json::int($data, 'display-index'),
        );
    }
}
