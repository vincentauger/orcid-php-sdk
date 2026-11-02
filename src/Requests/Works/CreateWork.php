<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Works;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Data\Works\WorkInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;
use VincentAuger\OrcidSdk\Traits\Requests\ReadsPutCodeFromLocation;

/**
 * Add a work (POST /{orcid}/work). Returns the put-code of the new item. A work whose `self`
 * external identifier this client already used fails with 409 (error 9021).
 */
final class CreateWork extends Request implements HasBody, RequiresMemberApi, RequiresScopes
{
    use HasJsonBody;
    use HasRecordEndpoint;
    use ReadsPutCodeFromLocation;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $orcid,
        private readonly WorkInput $input,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'work');
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
}
