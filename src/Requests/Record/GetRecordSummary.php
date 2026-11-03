<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Record;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Data\Record\RecordSummary;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get the record summary (GET /{orcid}/summary). Member API only, with any token that reads the record (a 3-legged
 * one or a /read-public client credentials one): the Public API answers 404, error 9001, even with a token (`c7/`).
 */
final class GetRecordSummary extends Request implements RequiresMemberApi
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'summary');
    }

    public function createDtoFromResponse(Response $response): RecordSummary
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return RecordSummary::fromArray($data);
    }
}
