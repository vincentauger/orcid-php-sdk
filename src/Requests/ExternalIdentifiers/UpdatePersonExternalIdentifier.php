<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\ExternalIdentifiers;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifier;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifierInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Replace an external identifier (PUT /{orcid}/external-identifiers/{put-code}). Only the client that created the item can
 * update it.
 */
final class UpdatePersonExternalIdentifier extends Request implements HasBody, RequiresMemberApi, RequiresScopes
{
    use HasJsonBody;
    use HasRecordEndpoint;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
        private readonly PersonExternalIdentifierInput $input,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'external-identifiers/'.$this->putCode);
    }

    /**
     * ORCID requires the put-code in the body too (400, error 9019 without it)
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->input->toArray($this->putCode);
    }

    public function requiredScopes(): array
    {
        return [Scope::PersonUpdate];
    }

    public function createDtoFromResponse(Response $response): PersonExternalIdentifier
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return PersonExternalIdentifier::fromArray($data) ?? throw new \UnexpectedValueException('ORCID returned an external identifier without a type or a value');
    }
}
