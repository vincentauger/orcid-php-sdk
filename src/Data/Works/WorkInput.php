<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Works;

use VincentAuger\OrcidSdk\Data\Common\Contributor;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\TranslatedTitle;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Enums\CitationType;
use VincentAuger\OrcidSdk\Enums\ContributorRole;
use VincentAuger\OrcidSdk\Enums\ContributorSequence;
use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Enums\WorkType;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Data to create or update a work.
 *
 * ORCID replaces the whole item on update, so every field you want to keep
 * must be sent again. Blank optional strings are treated as not set.
 *
 * Required by ORCID: a title, a type and an external identifier with the
 * relationship `self` (e.g. the DOI), which identifies the work: adding a
 * second work with the same `self` identifier fails (409, error 9021).
 */
final readonly class WorkInput
{
    public ?string $journalTitle;

    public ?string $shortDescription;

    public ?string $languageCode;

    public ?string $country;

    /**
     * @param  list<ExternalId>  $externalIds
     * @param  list<Contributor>  $contributors
     * @param  string|null  $journalTitle  The journal, book, series or conference the work was published in
     * @param  string|null  $languageCode  The language of the metadata, as an ORCID language code (e.g. "en")
     * @param  string|null  $country  ISO 3166-1 alpha-2 country code, e.g. "CA"
     */
    public function __construct(
        public Title $title,
        public WorkType $type,
        public array $externalIds,
        ?string $journalTitle = null,
        ?string $shortDescription = null,
        public ?Citation $citation = null,
        public ?FuzzyDate $publicationDate = null,
        public ?Url $url = null,
        public array $contributors = [],
        ?string $languageCode = null,
        ?string $country = null,
    ) {
        $this->journalTitle = Json::blankToNull($journalTitle);
        $this->shortDescription = Json::blankToNull($shortDescription);
        $this->languageCode = Json::blankToNull($languageCode);
        $country = Json::blankToNull($country);
        $this->country = $country === null ? null : strtoupper($country);

        $self = array_filter($externalIds, static fn (ExternalId $id): bool => $id->relationship === ExternalIdRelationship::Self);

        if ($self === []) {
            throw new \InvalidArgumentException("ORCID requires an external identifier with the relationship 'self' (error 9023)");
        }

        // ORCID's schema limits (error 9047)
        $this->assertMaxLength($title->title, 1000, 'title');
        $this->assertMaxLength($title->subtitle, 1000, 'subtitle');
        $this->assertMaxLength($title->translatedTitle?->title, 1000, 'translated title');
        $this->assertMaxLength($this->journalTitle, 1000, 'journal title');
        $this->assertMaxLength($this->shortDescription, 5000, 'short description');

        if ($title->translatedTitle instanceof TranslatedTitle) {
            $this->assertLanguageCode($title->translatedTitle->languageCode);
        }

        if ($this->languageCode !== null) {
            $this->assertLanguageCode($this->languageCode);
        }

        if ($this->country !== null && preg_match('/^[A-Z]{2}$/', $this->country) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid country "%s", expected an ISO 3166-1 alpha-2 code', $this->country));
        }

        if ($citation instanceof Citation && ! $citation->type instanceof CitationType) {
            throw new \InvalidArgumentException(sprintf('Unknown citation type "%s": use a CitationType', $citation->rawType));
        }

        foreach ($contributors as $contributor) {
            $this->assertSendable($contributor);
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
            'title' => $this->title->toArray(),
            'journal-title' => Json::wrap($this->journalTitle),
            'short-description' => $this->shortDescription,
            'citation' => $this->citation?->toArray(),
            'type' => $this->type->value,
            'publication-date' => $this->publicationDate?->toArray(),
            'external-ids' => ExternalId::listToArray($this->externalIds),
            'url' => $this->url?->toArray(),
            'contributors' => Contributor::listToArray($this->contributors),
            'language-code' => $this->languageCode,
            'country' => Json::wrap($this->country),
        ]);
    }

    private function assertSendable(Contributor $contributor): void
    {
        // ORCID rejects other roles (error 9058)
        if (! $contributor->role instanceof ContributorRole && $contributor->rawRole !== null) {
            throw new \InvalidArgumentException(sprintf('Unknown contributor role "%s": use a ContributorRole', $contributor->rawRole));
        }

        if (! $contributor->sequence instanceof ContributorSequence && $contributor->rawSequence !== null) {
            throw new \InvalidArgumentException(sprintf('Unknown contributor sequence "%s": use a ContributorSequence', $contributor->rawSequence));
        }

        $this->assertMaxLength($contributor->creditName, 150, 'contributor credit name');
    }

    /**
     * ORCID codes are ISO 639-1, plus zh_CN and zh_TW; ORCID also rejects unknown ones (error 9047)
     */
    private function assertLanguageCode(string $code): void
    {
        if (preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $code) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid language code "%s", expected e.g. "en" or "zh_CN"', $code));
        }
    }

    private function assertMaxLength(?string $value, int $max, string $field): void
    {
        if ($value !== null && mb_strlen($value) > $max) {
            throw new \InvalidArgumentException(sprintf('The %s is longer than %d characters', $field, $max));
        }
    }
}
