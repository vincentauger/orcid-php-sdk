<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Addresses;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\Address;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get one address (GET /{orcid}/address/{put-code})
 */
final class GetAddress extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'address/'.$this->putCode);
    }

    public function createDtoFromResponse(Response $response): Address
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return Address::fromArray($data) ?? throw new \UnexpectedValueException('ORCID returned an address without a country');
    }
}
