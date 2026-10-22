<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Activities;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Activities\ActivitiesSummary;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get the summary of every activity on a record (GET /{orcid}/activities)
 */
final class GetActivitiesSummary extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'activities');
    }

    public function createDtoFromResponse(Response $response): ActivitiesSummary
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return ActivitiesSummary::fromArray($data);
    }
}
