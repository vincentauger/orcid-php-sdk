<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\OtherNames;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Data\Person\OtherName;
use VincentAuger\OrcidSdk\Data\Person\OtherNameInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Replace an other name (PUT /{orcid}/other-names/{put-code}). Only the client that created the item can update it.
 */
final class UpdateOtherName extends Request implements HasBody, RequiresMemberApi, RequiresScopes
{
    use HasJsonBody;
    use HasRecordEndpoint;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
        private readonly OtherNameInput $input,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'other-names/'.$this->putCode);
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

    public function createDtoFromResponse(Response $response): OtherName
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return OtherName::fromArray($data) ?? throw new \UnexpectedValueException('ORCID returned an other name without content');
    }
}
