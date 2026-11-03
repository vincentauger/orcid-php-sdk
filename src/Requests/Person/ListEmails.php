<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Person;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Person\Email;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * List the emails of a record visible to the token (GET /{orcid}/email)
 */
final class ListEmails extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'email');
    }

    /**
     * @return list<Email>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return Email::listFromArray($data);
    }
}
