<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\PeerReviews;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewGroups;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * List the peer reviews of a record, grouped (GET /{orcid}/peer-reviews)
 */
final class ListPeerReviews extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $orcid,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'peer-reviews');
    }

    public function createDtoFromResponse(Response $response): PeerReviewGroups
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return PeerReviewGroups::fromArray($data);
    }
}
