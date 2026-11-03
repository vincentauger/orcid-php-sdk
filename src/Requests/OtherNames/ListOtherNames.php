<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\OtherNames;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\OtherName;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * List the other names of a record (GET /{orcid}/other-names)
 */
final class ListOtherNames extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'other-names');
    }

    /**
     * @return list<OtherName>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return OtherName::listFromArray($data);
    }
}
