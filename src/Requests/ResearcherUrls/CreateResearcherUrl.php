<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\ResearcherUrls;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Data\Person\ResearcherUrlInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;
use VincentAuger\OrcidSdk\Traits\Requests\ReadsPutCodeFromLocation;

/**
 * Add a researcher URL, a website (POST /{orcid}/researcher-urls). Returns the put-code of the new item. A URL this client already
 * added fails with 409 (error 9030).
 */
final class CreateResearcherUrl extends Request implements HasBody, RequiresMemberApi, RequiresScopes
{
    use HasJsonBody;
    use HasRecordEndpoint;
    use ReadsPutCodeFromLocation;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $orcid,
        private readonly ResearcherUrlInput $input,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'researcher-urls');
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
