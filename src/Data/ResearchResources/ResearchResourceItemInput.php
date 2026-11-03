<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\ResearchResources;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Enums\ResearchResourceType;
use VincentAuger\OrcidSdk\Support\Json;
use VincentAuger\OrcidSdk\Traits\Data\ValidatesHosts;

/**
 * A resource item of a research resource to create or update: one resource the researcher used, e.g. a vessel.
 *
 * Required by ORCID (error 9047 without them, `c4/s09`, `s10`, `s12`, `s13`): a non-blank name, a type, a host and
 * an external identifier of any relationship (`c4/s23`). A host follows the proposal's rules (`c4/s34`, `s35`).
 */
final readonly class ResearchResourceItemInput
{
    use ValidatesHosts;

    public string $name;

    /**
     * @param  list<Organization>  $hosts  Who provides the resource
     * @param  list<ExternalId>  $externalIds  The resource's identifiers, e.g. an RRID or your system's id for it
     */
    public function __construct(
        string $name,
        public ResearchResourceType $type,
        public array $hosts,
        public array $externalIds,
        public ?Url $url = null,
    ) {
        $this->name = Json::blankToNull($name) ?? throw new \InvalidArgumentException('ORCID requires a non-blank resource name (error 9047)');

        // ORCID's schema limit (error 9047, `c4/s26`)
        $this->assertMaxLength($this->name, 1000, 'resource name');
        $this->assertHosts($hosts, 'resource item');

        if ($externalIds === []) {
            throw new \InvalidArgumentException('A resource item requires an external identifier, e.g. an RRID (error 9047)');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::withoutNulls([
            'resource-name' => $this->name,
            'resource-type' => $this->type->value,
            'hosts' => Organization::listToArray($this->hosts),
            'external-ids' => ExternalId::listToArray($this->externalIds),
            'url' => $this->url?->toArray(),
        ]);
    }
}
