<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReview;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewSummary;
use VincentAuger\OrcidSdk\Enums\PeerReviewSubjectType;
use VincentAuger\OrcidSdk\Enums\PeerReviewType;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Exceptions\OrcidValidationException;

/*
 * Talks to the ORCID sandbox without fixtures (composer test:live): checks that ORCID still behaves as recorded.
 * Skipped without a .env file.
 */

it('creates, reads, updates, lists and deletes a peer review on the sandbox', function (): void {
    $this->requiresSandbox();

    $peerReviews = $this->getMemberApiClient()->peerReviews();
    $putCode = $peerReviews->create(testPeerReview(
        'live lifecycle',
        subjectExternalId: new ExternalId('doi', '10.5555/orcid-php-sdk-live-lifecycle'),
        subjectType: PeerReviewSubjectType::Report,
    ));

    try {
        $created = $peerReviews->get($putCode);
        $updated = $peerReviews->update($putCode, testPeerReview('live lifecycle', type: PeerReviewType::Evaluation));
        $list = $peerReviews->list();

        try {
            $peerReviews->create(testPeerReview('live lifecycle'));
            $duplicate = null;
        } catch (OrcidConflictException $exception) {
            $duplicate = $exception;
        }

        try {
            $peerReviews->create(testPeerReview('live unknown group', reviewGroupId: 'orcid-generated:orcid-php-sdk-unknown'));
            $unknownGroup = null;
        } catch (OrcidValidationException $exception) {
            $unknownGroup = $exception;
        }
    } finally {
        $peerReviews->delete($putCode);
    }

    $ours = array_values(array_filter($list->summaries(), fn (PeerReviewSummary $summary): bool => $summary->putCode === $putCode));

    expect($created->reviewIdentifiers[0]->value)->toBe('orcid-php-sdk-live-lifecycle')
        ->and($created->subjectType)->toBe(PeerReviewSubjectType::Report)
        ->and($this->isOurs($created->source))->toBeTrue()
        ->and($updated->type)->toBe(PeerReviewType::Evaluation)
        ->and($updated->subjectExternalId)->toBeNull()
        ->and($ours)->toHaveCount(1)
        ->and($ours[0]->reviewGroupId)->toBe(TEST_REVIEW_GROUP)
        ->and($duplicate?->existingPutCode())->toBe($putCode)
        ->and($unknownGroup?->errorCode)->toBe(9020)
        ->and(fn (): PeerReview => $peerReviews->get($putCode))->toThrow(OrcidNotFoundException::class);
})->group('live');
