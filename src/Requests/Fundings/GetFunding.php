<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Fundings;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Fundings\Funding;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get one funding in full (GET /{orcid}/funding/{put-code})
 */
final class GetFunding extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'funding/'.$this->putCode);
    }

    public function createDtoFromResponse(Response $response): Funding
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return Funding::fromArray($data);
    }
}
