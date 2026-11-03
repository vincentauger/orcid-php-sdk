<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Person;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\PersonalDetails;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get the name, other names and biography of a record (GET /{orcid}/personal-details)
 */
final class GetPersonalDetails extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'personal-details');
    }

    public function createDtoFromResponse(Response $response): PersonalDetails
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return PersonalDetails::fromArray($data);
    }
}
