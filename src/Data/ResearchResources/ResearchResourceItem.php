<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\ResearchResources;

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Enums\ResearchResourceType;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * A resource item of a research resource as returned by ORCID. Use ResearchResourceItemInput to send one.
 *
 * Reading never fails on what ORCID returns: a type the SDK does not know is kept in `rawType`, with a null `type`.
 */
final readonly class ResearchResourceItem
{
    /**
     * @param  list<Organization>  $hosts  Who provides the resource
     * @param  list<ExternalId>  $externalIds
     */
    public function __construct(
        public ?string $name,
        public ?ResearchResourceType $type,
        public ?string $rawType,
        public array $hosts = [],
        public array $externalIds = [],
        public ?Url $url = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Json::string($data, 'resource-name'),
            type: Json::enum($data, 'resource-type', ResearchResourceType::class),
            rawType: Json::string($data, 'resource-type'),
            hosts: Organization::listFromArray(Json::object($data, 'hosts')),
            externalIds: ExternalId::listFromArray(Json::object($data, 'external-ids')),
            url: Url::fromArray(Json::object($data, 'url')),
        );
    }
}
