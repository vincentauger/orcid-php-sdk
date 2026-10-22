<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Affiliations;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Data\Affiliations\AffiliationInput;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Replace an affiliation (PUT /{orcid}/employment/{put-code}, ...). Fields left
 * out of the input are cleared. Only the client that created the item can update it.
 */
final class UpdateAffiliation extends Request implements HasBody, RequiresMemberApi, RequiresScopes
{
    use HasJsonBody;
    use HasRecordEndpoint;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly string $orcid,
        private readonly AffiliationType $type,
        private readonly int $putCode,
        private readonly AffiliationInput $input,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, $this->type->itemEndpoint().'/'.$this->putCode);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->input->toArray($this->putCode);
    }

    public function requiredScopes(): array
    {
        return [Scope::ActivitiesUpdate];
    }

    public function createDtoFromResponse(Response $response): Affiliation
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return Affiliation::fromArray($this->type, $data);
    }
}
