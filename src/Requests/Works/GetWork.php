<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Works;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Works\Work;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get one work in full (GET /{orcid}/work/{put-code})
 */
final class GetWork extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'work/'.$this->putCode);
    }

    public function createDtoFromResponse(Response $response): Work
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return Work::fromArray($data);
    }
}
