<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Record;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Record\Record;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get the whole record: the person and activities sections (GET /{orcid}/record)
 */
final class GetRecord extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'record');
    }

    public function createDtoFromResponse(Response $response): Record
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return Record::fromArray($data);
    }
}
