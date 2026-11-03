<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Record;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * ORCID's short overview of a record (GET /{orcid}/summary, Member API only): how many items each activity section
 * holds, validated (added by a client) or self-asserted (added by the researcher), the record's affiliations and its
 * person external identifiers, in a shorter form than their sections'. Not the activities summary, which holds the
 * activity items themselves.
 */
final readonly class RecordSummary
{
    /**
     * @param  list<RecordSummaryAffiliation>  $employments
     * @param  list<RecordSummaryAffiliation>  $educationQualifications  Educations and qualifications
     * @param  list<RecordSummaryAffiliation>  $professionalActivities  Invited positions, distinctions, memberships and services
     * @param  list<RecordSummaryExternalIdentifier>  $externalIdentifiers
     */
    public function __construct(
        /** The record's ORCID iD */
        public string $orcid,
        public ?string $creditName = null,
        public array $employments = [],
        /** ORCID's count, which may differ from the number of items listed */
        public int $employmentCount = 0,
        public array $educationQualifications = [],
        public int $educationQualificationCount = 0,
        public array $professionalActivities = [],
        public int $professionalActivityCount = 0,
        public array $externalIdentifiers = [],
        public ActivityCounts $works = new ActivityCounts,
        public ActivityCounts $fundings = new ActivityCounts,
        public ActivityCounts $peerReviews = new ActivityCounts,
        /** ORCID's peer-review-publication-grants count, alongside the peer review counts */
        public int $peerReviewPublicationGrants = 0,
        public ActivityCounts $researchResources = new ActivityCounts,
        /** How many email domains the record shows */
        public int $emailDomainCount = 0,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $employments = Json::object($data, 'employments');
        $educationQualifications = Json::object($data, 'education-qualifications');
        $professionalActivities = Json::object($data, 'professional-activities');
        $peerReviews = Json::object($data, 'peer-reviews') ?? [];
        $peerReviewTotal = Json::int($peerReviews, 'total') ?? 0;
        $peerReviewsSelfAsserted = Json::int($peerReviews, 'self-asserted-count') ?? 0;

        return new self(
            orcid: Json::string(Json::object($data, 'orcid-identifier') ?? [], 'path')
                ?? throw new \UnexpectedValueException('ORCID record summary has no orcid-identifier'),
            creditName: Json::string($data, 'credit-name'),
            employments: RecordSummaryAffiliation::listFromArray($employments, 'employment'),
            employmentCount: Json::int($employments ?? [], 'count') ?? 0,
            educationQualifications: RecordSummaryAffiliation::listFromArray($educationQualifications, 'education-qualification'),
            educationQualificationCount: Json::int($educationQualifications ?? [], 'count') ?? 0,
            professionalActivities: RecordSummaryAffiliation::listFromArray($professionalActivities, 'professional-activity'),
            professionalActivityCount: Json::int($professionalActivities ?? [], 'count') ?? 0,
            externalIdentifiers: RecordSummaryExternalIdentifier::listFromArray(Json::object($data, 'external-identifiers')),
            works: self::counts($data, 'works'),
            fundings: self::counts($data, 'fundings'),
            // peer reviews come as a total, not a validated count
            peerReviews: new ActivityCounts(max(0, $peerReviewTotal - $peerReviewsSelfAsserted), $peerReviewsSelfAsserted),
            peerReviewPublicationGrants: Json::int($peerReviews, 'peer-review-publication-grants') ?? 0,
            researchResources: self::counts($data, 'research-resources'),
            emailDomainCount: Json::int(Json::object($data, 'email-domains') ?? [], 'count') ?? 0,
            createdAt: Json::timestamp($data, 'created-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function counts(array $data, string $section): ActivityCounts
    {
        $counts = Json::object($data, $section) ?? [];

        return new ActivityCounts(
            validated: Json::int($counts, 'validated-count') ?? 0,
            selfAsserted: Json::int($counts, 'self-asserted-count') ?? 0,
        );
    }
}
