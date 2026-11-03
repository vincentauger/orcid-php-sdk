<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\ExternalIdentifiers;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifierInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;
use VincentAuger\OrcidSdk\Traits\Requests\ReadsPutCodeFromLocation;

/**
 * Add an external identifier (POST /{orcid}/external-identifiers). Returns the put-code of the new item. An
 * identifier whose type and value this client already added fails with 409 (error 9030).
 */
final class CreatePersonExternalIdentifier extends Request implements HasBody, RequiresMemberApi, RequiresScopes
{
    use HasJsonBody;
    use HasRecordEndpoint;
    use ReadsPutCodeFromLocation;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $orcid,
        private readonly PersonExternalIdentifierInput $input,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'external-identifiers');
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->input->toArray();
    }

    public function requiredScopes(): array
    {
        return [Scope::PersonUpdate];
    }
}
