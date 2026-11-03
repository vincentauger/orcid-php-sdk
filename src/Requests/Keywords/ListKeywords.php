<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Keywords;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\Keyword;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * List the keywords of a record (GET /{orcid}/keywords)
 */
final class ListKeywords extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'keywords');
    }

    /**
     * @return list<Keyword>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return Keyword::listFromArray($data);
    }
}
