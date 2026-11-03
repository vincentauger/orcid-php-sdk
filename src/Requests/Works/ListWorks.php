<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Works;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Works\WorkGroups;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * List the works of a record, grouped (GET /{orcid}/works)
 */
final class ListWorks extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'works');
    }

    public function createDtoFromResponse(Response $response): WorkGroups
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return WorkGroups::fromArray($data);
    }
}
