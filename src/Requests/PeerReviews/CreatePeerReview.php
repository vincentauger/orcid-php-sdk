<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\PeerReviews;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;
use VincentAuger\OrcidSdk\Traits\Requests\ReadsPutCodeFromLocation;

/**
 * Add a peer review (POST /{orcid}/peer-review). Returns the put-code of the new item. A peer review whose `self` review
 * identifier this client already used fails with 409 (error 9021, `c3/r21`).
 */
final class CreatePeerReview extends Request implements HasBody, RequiresMemberApi, RequiresScopes
{
    use HasJsonBody;
    use HasRecordEndpoint;
    use ReadsPutCodeFromLocation;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $orcid,
        private readonly PeerReviewInput $input,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'peer-review');
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
