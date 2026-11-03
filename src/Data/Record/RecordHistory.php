<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Record;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * How and when a record was created, as part of the whole record
 */
final readonly class RecordHistory
{
    public function __construct(
        /** ORCID's creation method, e.g. "DIRECT" (the researcher registered on orcid.org) */
        public ?string $creationMethod = null,
        public ?\DateTimeImmutable $submittedAt = null,
        public ?\DateTimeImmutable $completedAt = null,
        public ?\DateTimeImmutable $lastModifiedAt = null,
        /** Whether the researcher claimed the record (false for one a member created and they never claimed) */
        public ?bool $claimed = null,
        public ?\DateTimeImmutable $deactivatedAt = null,
        public ?bool $verifiedEmail = null,
        public ?bool $verifiedPrimaryEmail = null,
    ) {}

    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(
            creationMethod: Json::string($data, 'creation-method'),
            submittedAt: Json::timestamp($data, 'submission-date'),
            completedAt: Json::timestamp($data, 'completion-date'),
            lastModifiedAt: Json::timestamp($data, 'last-modified-date'),
            claimed: Json::bool($data, 'claimed'),
            deactivatedAt: Json::timestamp($data, 'deactivation-date'),
            verifiedEmail: Json::bool($data, 'verified-email'),
            verifiedPrimaryEmail: Json::bool($data, 'verified-primary-email'),
        );
    }
}
