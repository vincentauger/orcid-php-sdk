<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Works;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Enums\WorkType;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * A work as listed in a group: no contributors, citation or description. Read the full work by put-code.
 *
 * Reading never fails on what ORCID returns: a type the SDK does not know is kept in `rawType`, with a null `type`.
 */
final readonly class WorkSummary
{
    /**
     * @param  list<ExternalId>  $externalIds  With ORCID's normalized values, to compare them across sources
     */
    public function __construct(
        public int $putCode,
        public ?Title $title,
        public ?WorkType $type,
        public ?string $rawType,
        public array $externalIds = [],
        public ?string $journalTitle = null,
        public ?FuzzyDate $publicationDate = null,
        public ?Url $url = null,
        public ?Source $source = null,
        public ?Visibility $visibility = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
        /** The highest display index in a group marks the preferred summary (WorkGroup::preferred()) */
        public ?int $displayIndex = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID work summary has no put-code'),
            title: Title::fromArray(Json::object($data, 'title')),
            type: Json::enum($data, 'type', WorkType::class),
            rawType: Json::string($data, 'type'),
            externalIds: ExternalId::listFromArray(Json::object($data, 'external-ids')),
            journalTitle: Json::string($data, 'journal-title'),
            publicationDate: FuzzyDate::fromArray(Json::object($data, 'publication-date')),
            url: Url::fromArray(Json::object($data, 'url')),
            source: Source::fromArray(Json::object($data, 'source')),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
            displayIndex: Json::int($data, 'display-index'),
        );
    }
}
