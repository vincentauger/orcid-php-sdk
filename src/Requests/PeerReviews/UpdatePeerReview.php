<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\PeerReviews;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReview;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Replace a peer review (PUT /{orcid}/peer-review/{put-code}). Fields left out of the input are cleared.
 * Only the client that created the item can update it.
 */
final class UpdatePeerReview extends Request implements HasBody, RequiresMemberApi, RequiresScopes
{
    use HasJsonBody;
    use HasRecordEndpoint;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
        private readonly PeerReviewInput $input,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'peer-review/'.$this->putCode);
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
        return [Scope::ActivitiesUpdate];
    }

    public function createDtoFromResponse(Response $response): PeerReview
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return PeerReview::fromArray($data);
    }
}
