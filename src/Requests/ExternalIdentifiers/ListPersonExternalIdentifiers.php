<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\ExternalIdentifiers;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifier;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * List the external identifiers of a record (GET /{orcid}/external-identifiers)
 */
final class ListPersonExternalIdentifiers extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'external-identifiers');
    }

    /**
     * @return list<PersonExternalIdentifier>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return PersonExternalIdentifier::listFromArray($data);
    }
}
