<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\OtherNames;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\OtherName;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get one other name (GET /{orcid}/other-names/{put-code})
 */
final class GetOtherName extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'other-names/'.$this->putCode);
    }

    public function createDtoFromResponse(Response $response): OtherName
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return OtherName::fromArray($data) ?? throw new \UnexpectedValueException('ORCID returned an other name without content');
    }
}
