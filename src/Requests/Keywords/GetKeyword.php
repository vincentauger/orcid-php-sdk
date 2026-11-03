<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Keywords;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\Keyword;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get one keyword (GET /{orcid}/keywords/{put-code})
 */
final class GetKeyword extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'keywords/'.$this->putCode);
    }

    public function createDtoFromResponse(Response $response): Keyword
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return Keyword::fromArray($data) ?? throw new \UnexpectedValueException('ORCID returned a keyword without content');
    }
}
