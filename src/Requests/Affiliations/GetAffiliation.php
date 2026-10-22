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
 * Get one affiliation (GET /{orcid}/employment/{put-code}, ...)
 */
final class GetAffiliation extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
        private readonly AffiliationType $type,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, $this->type->itemEndpoint().'/'.$this->putCode);
    }

    public function createDtoFromResponse(Response $response): Affiliation
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return Affiliation::fromArray($this->type, $data);
    }
}
