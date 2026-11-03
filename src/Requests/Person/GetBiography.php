<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Person;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\Biography;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get the biography of a record (GET /{orcid}/biography)
 */
final class GetBiography extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'biography');
    }

    /**
     * @return Biography|null null for a missing or blank biography, or one not visible to the token
     */
    public function createDtoFromResponse(Response $response): ?Biography
    {
        /** @var array<string, mixed>|null $data */
        $data = $response->json();

        return Biography::fromArray($data);
    }
}
