<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\PeerReviews;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Enums\PeerReviewRole;
use VincentAuger\OrcidSdk\Enums\PeerReviewType;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * A peer review as listed in a group: nothing about the subject (`c3/r35`). Read the full peer review by put-code.
 *
 * Reading never fails on what ORCID returns: a role or type the SDK does not know is kept in its raw field, with a
 * null enum.
 */
final readonly class PeerReviewSummary
{
    /**
     * @param  list<ExternalId>  $reviewIdentifiers
     * @param  string|null  $reviewGroupId  The review group, e.g. "issn:0706-652X"
     */
    public function __construct(
        public int $putCode,
        public ?PeerReviewRole $role,
        public ?string $rawRole,
        public ?PeerReviewType $type,
        public ?string $rawType,
        public array $reviewIdentifiers = [],
        public ?Url $reviewUrl = null,
        public ?FuzzyDate $completionDate = null,
        public ?string $reviewGroupId = null,
        public ?Organization $conveningOrganization = null,
        public ?Source $source = null,
        public ?Visibility $visibility = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        public ?string $path = null,
        /** The highest display index in a group marks the preferred summary (PeerReviewGroup::preferred()) */
        public ?int $displayIndex = null,
    ) {}

    /**
     * ORCID names some fields differently than in the full peer review: "external-ids", "completion-date"
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID peer review summary has no put-code'),
            role: Json::enum($data, 'reviewer-role', PeerReviewRole::class),
            rawRole: Json::string($data, 'reviewer-role'),
            type: Json::enum($data, 'review-type', PeerReviewType::class),
            rawType: Json::string($data, 'review-type'),
            reviewIdentifiers: ExternalId::listFromArray(Json::object($data, 'external-ids')),
            reviewUrl: Url::fromArray(Json::object($data, 'review-url')),
            completionDate: FuzzyDate::fromArray(Json::object($data, 'completion-date')),
            reviewGroupId: Json::string($data, 'review-group-id'),
            conveningOrganization: Organization::fromArray(Json::object($data, 'convening-organization')),
            source: Source::fromArray(Json::object($data, 'source')),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
            displayIndex: Json::int($data, 'display-index'),
        );
    }
}
