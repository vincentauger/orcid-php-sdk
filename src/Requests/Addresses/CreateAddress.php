<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Addresses;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Data\Person\AddressInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;
use VincentAuger\OrcidSdk\Traits\Requests\ReadsPutCodeFromLocation;

/**
 * Add an address (POST /{orcid}/address). Returns the put-code of the new item. ORCID only stores
 * its country, and accepts the same country twice.
 */
final class CreateAddress extends Request implements HasBody, RequiresMemberApi, RequiresScopes
{
    use HasJsonBody;
    use HasRecordEndpoint;
    use ReadsPutCodeFromLocation;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $orcid,
        private readonly AddressInput $input,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'address');
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
