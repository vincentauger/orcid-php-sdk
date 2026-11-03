<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\ExternalIdentifiers;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifier;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get one external identifier (GET /{orcid}/external-identifiers/{put-code})
 */
final class GetPersonExternalIdentifier extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'external-identifiers/'.$this->putCode);
    }

    public function createDtoFromResponse(Response $response): PersonExternalIdentifier
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return PersonExternalIdentifier::fromArray($data) ?? throw new \UnexpectedValueException('ORCID returned an external identifier without a type or a value');
    }
}
