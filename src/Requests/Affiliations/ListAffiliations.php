<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Affiliations;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * List the affiliations of one type on a record (GET /{orcid}/employments, ...)
 */
final class ListAffiliations extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
        private readonly AffiliationType $type,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, $this->type->listEndpoint());
    }

    /**
     * @return list<Affiliation>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return Affiliation::listFromArray($this->type, $data);
    }
}
