<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\PeerReviews;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\TranslatedTitle;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Enums\PeerReviewRole;
use VincentAuger\OrcidSdk\Enums\PeerReviewSubjectType;
use VincentAuger\OrcidSdk\Enums\PeerReviewType;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Data to create or update a peer review.
 *
 * ORCID replaces the whole item on update, so every field you want to keep must be sent again. Blank optional
 * strings are treated as not set.
 *
 * Required by ORCID (error 9047 without them, `c3/r02`-`r08`, `r11`): the reviewer's role, the review type, a review
 * identifier of any relationship, the completion date, the review group id and the convening organization, with a
 * name and an address (a city or a country). Unlike a funding's, its disambiguated organization is optional
 * (`c3/r09`). Sending a `self` review identifier this client already used fails (409, error 9021, `c3/r21`).
 *
 * How open the review is depends on the optional fields: the required ones only for an anonymous review, plus the
 * subject fields to name the reviewed item, plus a public review identifier (e.g. a DOI) and `reviewUrl` for a
 * published review.
 *
 * ORCID groups a record's peer reviews by review group id, then by `self` review identifier: the subject's
 * identifier groups nothing (`c3/r35`).
 */
final readonly class PeerReviewInput
{
    public ?string $subjectContainerName;

    /**
     * @param  list<ExternalId>  $reviewIdentifiers  The review's own identifiers, e.g. your system's id for it
     *                                               (type "source-work-id")
     * @param  string  $reviewGroupId  The review group, usually the journal: "issn:" and its ISSN, e.g. "issn:0706-652X".
     *                                 ORCID creates an ISSN group on first use (`c3/r15`); other groups ("ringgold:",
     *                                 "orcid-generated:", "fundref:", "publons:") must exist (400, error 9020, `c3/r13`)
     * @param  Organization  $conveningOrganization  Who organized the review, usually the publisher
     * @param  ExternalId|null  $subjectExternalId  The reviewed item's identifier, e.g. its DOI
     * @param  string|null  $subjectContainerName  E.g. the journal's name
     * @param  Title|null  $subjectName  The reviewed item's title, without subtitle
     */
    public function __construct(
        public PeerReviewRole $role,
        public PeerReviewType $type,
        public array $reviewIdentifiers,
        public FuzzyDate $completionDate,
        public string $reviewGroupId,
        public Organization $conveningOrganization,
        public ?Url $reviewUrl = null,
        public ?ExternalId $subjectExternalId = null,
        ?string $subjectContainerName = null,
        public ?PeerReviewSubjectType $subjectType = null,
        public ?Title $subjectName = null,
        public ?Url $subjectUrl = null,
    ) {
        $this->subjectContainerName = Json::blankToNull($subjectContainerName);

        if ($reviewIdentifiers === []) {
            throw new \InvalidArgumentException('ORCID requires a review identifier (error 9047)');
        }

        if (Json::blankToNull($conveningOrganization->name) === null) {
            throw new \InvalidArgumentException('ORCID requires a non-blank organization name (error 9047)');
        }

        // ORCID only requires the address element, but an empty one would be sent as a JSON list
        $address = $conveningOrganization->address;

        if (Json::blankToNull($address?->city) === null && Json::blankToNull($address?->country) === null) {
            throw new \InvalidArgumentException('ORCID requires an organization address, with a city or a country (error 9047)');
        }

        $this->assertGroupId($reviewGroupId);

        // ORCID drops it without an error (`c3/r16`)
        if ($subjectName?->subtitle !== null) {
            throw new \InvalidArgumentException('A peer review subject name has no subtitle: ORCID drops it');
        }

        // ORCID's schema limits (error 9047, `c3/r32`)
        $this->assertMaxLength($subjectName?->title, 1000, 'subject title');
        $this->assertMaxLength($subjectName?->translatedTitle?->title, 1000, 'translated subject title');
        $this->assertMaxLength($this->subjectContainerName, 1000, 'subject container name');

        // ORCID codes are ISO 639-1, plus zh_CN and zh_TW; ORCID also rejects unknown ones (error 9047)
        $translatedTitle = $subjectName?->translatedTitle;

        if ($translatedTitle instanceof TranslatedTitle && preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $translatedTitle->languageCode) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid language code "%s", expected e.g. "en" or "zh_CN"', $translatedTitle->languageCode));
        }
    }

    /**
     * ORCID JSON body. For an update, pass the item's put-code.
     *
     * @return array<string, mixed>
     */
    public function toArray(?int $putCode = null): array
    {
        return Json::withoutNulls([
            'put-code' => $putCode,
            'reviewer-role' => $this->role->value,
            'review-identifiers' => ExternalId::listToArray($this->reviewIdentifiers),
            'review-url' => $this->reviewUrl?->toArray(),
            'review-type' => $this->type->value,
            'review-completion-date' => $this->completionDate->toArray(),
            'review-group-id' => $this->reviewGroupId,
            'subject-external-identifier' => $this->subjectExternalId?->toArray(),
            'subject-container-name' => Json::wrap($this->subjectContainerName),
            'subject-type' => $this->subjectType?->value,
            'subject-name' => $this->subjectName?->toArray(),
            'subject-url' => $this->subjectUrl?->toArray(),
            'convening-organization' => $this->conveningOrganization->toArray(),
        ]);
    }

    /**
     * ORCID's group id pattern (error 9047, `c3/r14`, `r30`), and a valid ISSN for an "issn:" group (error 9053,
     * `c3/r12`, `r37`). ORCID checks the check digit, not that a journal has the ISSN (`c3/r28`).
     */
    private function assertGroupId(string $reviewGroupId): void
    {
        if (preg_match('/^(ringgold|issn|orcid-generated|fundref|publons):[0-9a-zA-Z^._~:\/?#\[\]@!$&\'()*+,;=-]{2,}$/', $reviewGroupId) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid review group id "%s", expected e.g. "issn:0706-652X" (error 9047)', $reviewGroupId));
        }

        if (str_starts_with($reviewGroupId, 'issn:') && ! $this->isIssn(substr($reviewGroupId, 5))) {
            throw new \InvalidArgumentException(sprintf('Invalid ISSN "%s" (error 9053)', substr($reviewGroupId, 5)));
        }
    }

    private function isIssn(string $issn): bool
    {
        if (preg_match('/^(\d{4})-(\d{3})([\dX])$/i', $issn, $matches) !== 1) {
            return false;
        }

        $digits = $matches[1].$matches[2];
        $sum = 0;

        foreach (str_split($digits) as $i => $digit) {
            $sum += (int) $digit * (8 - $i);
        }

        $check = (11 - $sum % 11) % 11;

        return strtoupper($matches[3]) === ($check === 10 ? 'X' : (string) $check);
    }

    private function assertMaxLength(?string $value, int $max, string $field): void
    {
        if ($value !== null && mb_strlen($value) > $max) {
            throw new \InvalidArgumentException(sprintf('The %s is longer than %d characters', $field, $max));
        }
    }
}
