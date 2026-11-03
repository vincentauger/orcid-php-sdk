<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReview;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewGroups;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\PeerReviews\GetPeerReview;
use VincentAuger\OrcidSdk\Requests\PeerReviews\ListPeerReviews;

/**
 * Reads on the peer reviews of any record: $orcid->record($id)->peerReviews().
 * Every method throws an OrcidException on failure.
 */
final readonly class ReadOnlyPeerReviewsResource
{
    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {}

    /**
     * The record's peer reviews as groups: preferred() to display them, summaries() to sync, byReviewGroup() to show
     * them journal by journal
     */
    public function list(): PeerReviewGroups
    {
        $request = new ListPeerReviews($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * One peer review in full, by put-code
     */
    public function get(int $putCode): PeerReview
    {
        $request = new GetPeerReview($this->orcid, $putCode);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }
}
