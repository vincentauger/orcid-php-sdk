<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\PeerReviews;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Enums\PeerReviewRole;
use VincentAuger\OrcidSdk\Enums\PeerReviewSubjectType;
use VincentAuger\OrcidSdk\Enums\PeerReviewType;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * A peer review as returned by ORCID, read in full by put-code. Use PeerReviewInput to create or update one.
 *
 * Reading never fails on what ORCID returns: a role, type or subject type the SDK does not know is kept in its raw
 * field, with a null enum.
 */
final readonly class PeerReview
{
    /**
     * @param  list<ExternalId>  $reviewIdentifiers
     * @param  string|null  $reviewGroupId  The review group, e.g. "issn:0706-652X"
     * @param  ExternalId|null  $subjectExternalId  The reviewed item's identifier
     * @param  Title|null  $subjectName  The reviewed item's title
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
        public ?ExternalId $subjectExternalId = null,
        public ?string $subjectContainerName = null,
        public ?PeerReviewSubjectType $subjectType = null,
        public ?string $rawSubjectType = null,
        public ?Title $subjectName = null,
        public ?Url $subjectUrl = null,
        public ?Organization $conveningOrganization = null,
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
        $subjectExternalId = Json::object($data, 'subject-external-identifier');

        return new self(
            putCode: Json::int($data, 'put-code') ?? throw new \UnexpectedValueException('ORCID peer review has no put-code'),
            role: Json::enum($data, 'reviewer-role', PeerReviewRole::class),
            rawRole: Json::string($data, 'reviewer-role'),
            type: Json::enum($data, 'review-type', PeerReviewType::class),
            rawType: Json::string($data, 'review-type'),
            reviewIdentifiers: ExternalId::listFromArray(Json::object($data, 'review-identifiers')),
            reviewUrl: Url::fromArray(Json::object($data, 'review-url')),
            completionDate: FuzzyDate::fromArray(Json::object($data, 'review-completion-date')),
            reviewGroupId: Json::string($data, 'review-group-id'),
            subjectExternalId: $subjectExternalId === null ? null : ExternalId::fromArray($subjectExternalId),
            subjectContainerName: Json::string($data, 'subject-container-name'),
            subjectType: Json::enum($data, 'subject-type', PeerReviewSubjectType::class),
            rawSubjectType: Json::string($data, 'subject-type'),
            subjectName: Title::fromArray(Json::object($data, 'subject-name')),
            subjectUrl: Url::fromArray(Json::object($data, 'subject-url')),
            conveningOrganization: Organization::fromArray(Json::object($data, 'convening-organization')),
            source: Source::fromArray(Json::object($data, 'source')),
            visibility: Json::enum($data, 'visibility', Visibility::class),
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            path: Json::string($data, 'path'),
        );
    }
}
