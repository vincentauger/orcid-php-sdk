<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\ResearchResources;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * The proposal of a research resource as returned by ORCID, in full and in summaries: what the researcher was
 * granted access for. Use ResearchResourceProposalInput to send one.
 */
final readonly class ResearchResourceProposal
{
    /**
     * @param  list<Organization>  $hosts  Who granted the access
     * @param  list<ExternalId>  $externalIds
     */
    public function __construct(
        public ?Title $title,
        public array $hosts = [],
        public array $externalIds = [],
        public ?FuzzyDate $startDate = null,
        public ?FuzzyDate $endDate = null,
        public ?Url $url = null,
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
            title: Title::fromArray(Json::object($data, 'title')),
            hosts: Organization::listFromArray(Json::object($data, 'hosts')),
            externalIds: ExternalId::listFromArray(Json::object($data, 'external-ids')),
            startDate: FuzzyDate::fromArray(Json::object($data, 'start-date')),
            endDate: FuzzyDate::fromArray(Json::object($data, 'end-date')),
            url: Url::fromArray(Json::object($data, 'url')),
        );
    }
}
