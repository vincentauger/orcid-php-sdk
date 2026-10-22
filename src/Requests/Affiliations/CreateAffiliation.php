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
use VincentAuger\OrcidSdk\Data\Affiliations\AffiliationInput;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Add an affiliation (POST /{orcid}/employment, ...). ORCID answers 201 with an
 * empty body; the new put-code is the last segment of the Location header.
 */
final class CreateAffiliation extends Request implements HasBody, RequiresMemberApi, RequiresScopes
{
    use HasJsonBody;
    use HasRecordEndpoint;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $orcid,
        private readonly AffiliationType $type,
        private readonly AffiliationInput $input,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, $this->type->itemEndpoint());
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
        return [Scope::ActivitiesUpdate];
    }

    /**
     * The put-code of the new item
     */
    public function createDtoFromResponse(Response $response): int
    {
        $location = $response->header('Location');

        if (! is_string($location) || preg_match('#/(\d+)/?$#', $location, $matches) !== 1) {
            throw new \UnexpectedValueException('ORCID did not return the put-code of the new item (no Location header)');
        }

        return (int) $matches[1];
    }
}
