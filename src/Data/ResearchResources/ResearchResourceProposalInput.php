<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\ResearchResources;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\TranslatedTitle;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Support\Json;
use VincentAuger\OrcidSdk\Traits\Data\ValidatesHosts;

/**
 * The proposal of a research resource to create or update: what the researcher was granted access for, e.g. ship
 * time on a vessel.
 *
 * Required by ORCID (error 9047 without them, `c4/s03`, `s04`, `s06`): a title without subtitle, a host and an
 * external identifier of any relationship (`c4/s07`; unlike a work, `self` is not required). A host needs a name, an
 * address (a city or a country) and a disambiguated organization (9045, `c4/s14`). A start date is required whenever
 * an end date is given (9050, `c4/s17`).
 */
final readonly class ResearchResourceProposalInput
{
    use ValidatesHosts;

    /**
     * @param  list<Organization>  $hosts  Who granted the access
     * @param  list<ExternalId>  $externalIds  Usually the proposal's id in your system (type "proposal-id"), as `self`
     */
    public function __construct(
        public Title $title,
        public array $hosts,
        public array $externalIds,
        public ?FuzzyDate $startDate = null,
        public ?FuzzyDate $endDate = null,
        public ?Url $url = null,
    ) {
        $this->assertHosts($hosts, 'proposal');

        if ($externalIds === []) {
            throw new \InvalidArgumentException('A proposal requires an external identifier, e.g. its id in your system (error 9047)');
        }

        if ($endDate instanceof FuzzyDate && ! $startDate instanceof FuzzyDate) {
            throw new \InvalidArgumentException('ORCID requires a start date when an end date is given (error 9050)');
        }

        // ORCID accepts it (`c4/s18`)
        if ($startDate instanceof FuzzyDate && $endDate instanceof FuzzyDate && $endDate->isBefore($startDate)) {
            throw new \InvalidArgumentException(sprintf('The end date (%s) is before the start date (%s)', $endDate, $startDate));
        }

        if ($title->subtitle !== null) {
            throw new \InvalidArgumentException('A proposal title has no subtitle: ORCID refuses one (error 9047)');
        }

        // ORCID's schema limits (error 9047, `c4/s25`)
        $this->assertMaxLength($title->title, 1000, 'title');
        $this->assertMaxLength($title->translatedTitle?->title, 1000, 'translated title');

        // ORCID codes are ISO 639-1, plus zh_CN and zh_TW; ORCID also rejects unknown ones (error 9047)
        if ($title->translatedTitle instanceof TranslatedTitle && preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $title->translatedTitle->languageCode) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid language code "%s", expected e.g. "en" or "zh_CN"', $title->translatedTitle->languageCode));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::withoutNulls([
            'title' => $this->title->toArray(),
            'hosts' => Organization::listToArray($this->hosts),
            'external-ids' => ExternalId::listToArray($this->externalIds),
            'start-date' => $this->startDate?->toArray(),
            'end-date' => $this->endDate?->toArray(),
            'url' => $this->url?->toArray(),
        ]);
    }
}
