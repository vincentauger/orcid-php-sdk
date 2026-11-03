<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\PeerReviews;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReview;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get one peer review in full (GET /{orcid}/peer-review/{put-code})
 */
final class GetPeerReview extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
        private readonly int $putCode,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'peer-review/'.$this->putCode);
    }

    public function createDtoFromResponse(Response $response): PeerReview
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return PeerReview::fromArray($data);
    }
}
