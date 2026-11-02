<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Works;

use VincentAuger\OrcidSdk\Data\Common\Contributor;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Enums\WorkType;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * A work as returned by ORCID, read in full by put-code. Use WorkInput to create or update one.
 *
 * Reading never fails on what ORCID returns: a type the SDK does not know is kept in `rawType`, with a null `type`.
 */
final readonly class Work
{
    /**
     * @param  list<ExternalId>  $externalIds
     * @param  list<Contributor>  $contributors
     */
    public function __construct(
        public int $putCode,
        public ?Title $title,
        public ?WorkType $type,
        public ?string $rawType,
        public ?string $journalTitle = null,
        public ?string $shortDescription = null,
        public ?Citation $citation = null,
        public ?FuzzyDate $publicationDate = null,
        public array $externalIds = [],
        public ?Url $url = null,
        public array $contributors = [],
        public ?string $languageCode = null,
        public ?string $country = null,
        public ?Source $source = null,
        public ?Visibility $visibility = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID work has no put-code'),
            title: Title::fromArray(Json::object($data, 'title')),
            type: Json::enum($data, 'type', WorkType::class),
            rawType: Json::string($data, 'type'),
            journalTitle: Json::string($data, 'journal-title'),
            shortDescription: Json::string($data, 'short-description'),
            citation: Citation::fromArray(Json::object($data, 'citation')),
            publicationDate: FuzzyDate::fromArray(Json::object($data, 'publication-date')),
            externalIds: ExternalId::listFromArray(Json::object($data, 'external-ids')),
            url: Url::fromArray(Json::object($data, 'url')),
            contributors: Contributor::listFromArray(Json::object($data, 'contributors')),
            languageCode: Json::string($data, 'language-code'),
            country: Json::string($data, 'country'),
            source: Source::fromArray(Json::object($data, 'source')),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
        );
    }
}
