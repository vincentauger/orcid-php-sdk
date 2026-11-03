<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Record\Record;
use VincentAuger\OrcidSdk\Data\Record\RecordSummary;

it('reads a record summary without sections', function (): void {
    // the sandbox record's summary has null external identifiers and email domains (c7/s01)
    $summary = RecordSummary::fromArray([
        'orcid-identifier' => ['path' => '0000-0002-1825-0097'],
        'external-identifiers' => null,
        'email-domains' => ['count' => 0, 'email-domain' => null],
    ]);

    expect($summary->orcid)->toBe('0000-0002-1825-0097')
        ->and($summary->employments)->toBe([])
        ->and($summary->employmentCount)->toBe(0)
        ->and($summary->externalIdentifiers)->toBe([])
        ->and($summary->works->total())->toBe(0)
        ->and($summary->createdAt)->toBeNull();
});

it('splits the peer review total into validated and self-asserted counts', function (): void {
    $summary = RecordSummary::fromArray([
        'orcid-identifier' => ['path' => '0000-0002-1825-0097'],
        'peer-reviews' => ['peer-review-publication-grants' => 2, 'self-asserted-count' => 1, 'total' => 3],
    ]);

    expect($summary->peerReviews->validated)->toBe(2)
        ->and($summary->peerReviews->selfAsserted)->toBe(1)
        ->and($summary->peerReviews->total())->toBe(3)
        ->and($summary->peerReviewPublicationGrants)->toBe(2);
});

it('refuses a record or a record summary without an ORCID iD', function (string $class): void {
    $class::fromArray(['person' => []]);
})->with([Record::class, RecordSummary::class])->throws(UnexpectedValueException::class, 'has no orcid-identifier');

it('reads a whole record without sections', function (): void {
    $record = Record::fromArray(['orcid-identifier' => ['path' => '0000-0002-1825-0097']]);

    expect($record->person->keywords)->toBe([])
        ->and($record->activities->workCount())->toBe(0)
        ->and($record->history)->toBeNull()
        ->and($record->locale)->toBeNull();
});
