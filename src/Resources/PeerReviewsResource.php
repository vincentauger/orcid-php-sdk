<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReview;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewGroups;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewInput;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\PeerReviews\CreatePeerReview;
use VincentAuger\OrcidSdk\Requests\PeerReviews\DeletePeerReview;
use VincentAuger\OrcidSdk\Requests\PeerReviews\UpdatePeerReview;

/**
 * Peer reviews of the record a member connector is bound to: $orcid->peerReviews().
 * Every method throws an OrcidException on failure.
 */
final readonly class PeerReviewsResource
{
    private ReadOnlyPeerReviewsResource $readOnly;

    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {
        $this->readOnly = new ReadOnlyPeerReviewsResource($connector, $orcid);
    }

    /**
     * The record's peer reviews as groups: preferred() to display them, summaries() to sync, byReviewGroup() to show
     * them journal by journal
     */
    public function list(): PeerReviewGroups
    {
        return $this->readOnly->list();
    }

    /**
     * One peer review in full, by put-code
     */
    public function get(int $putCode): PeerReview
    {
        return $this->readOnly->get($putCode);
    }

    /**
     * @return int The put-code of the new item: store it to update or delete the item later
     *
     * @throws OrcidConflictException when this client already added a peer review with
     *                                the same `self` review identifier
     *                                (`isDuplicate()`, `existingPutCode()`)
     */
    public function create(PeerReviewInput $input): int
    {
        $request = new CreatePeerReview($this->orcid, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Replace a peer review created by this client. Fields left out of $input are cleared.
     */
    public function update(int $putCode, PeerReviewInput $input): PeerReview
    {
        $request = new UpdatePeerReview($this->orcid, $putCode, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function delete(int $putCode): void
    {
        $this->connector->sendOrFail(new DeletePeerReview($this->orcid, $putCode));
    }
}
