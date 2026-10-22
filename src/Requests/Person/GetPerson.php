<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Person;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\Person;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get the person section of a record (GET /{orcid}/person)
 */
final class GetPerson extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'person');
    }

    public function createDtoFromResponse(Response $response): Person
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return Person::fromArray($data);
    }
}
