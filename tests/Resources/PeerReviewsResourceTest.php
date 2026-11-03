<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Response;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\TranslatedTitle;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReview;
use VincentAuger\OrcidSdk\Enums\PeerReviewRole;
use VincentAuger\OrcidSdk\Enums\PeerReviewSubjectType;
use VincentAuger\OrcidSdk\Enums\PeerReviewType;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Exceptions\MissingScopeException;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;
use VincentAuger\OrcidSdk\Exceptions\OrcidValidationException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\PeerReviews\CreatePeerReview;

/*
 * Recorded against the ORCID sandbox. Every test only touches peer reviews it created
 * (review identifiers start with OrcidFixture::TEST_MARKER) and deletes them.
 * Each test's fixtures are a chain: to re-record, delete the whole folder.
 */

it('creates, reads, updates, lists and deletes a peer review', function (): void {
    $folder = 'peer-reviews/lifecycle';
    $mockClient = new MockClient([
        new OrcidFixture("{$folder}/create"),
        new OrcidFixture("{$folder}/get"),
        new OrcidFixture("{$folder}/get-public"),
        new OrcidFixture("{$folder}/update"),
        new OrcidFixture("{$folder}/list"),
        new OrcidFixture("{$folder}/list-public"),
        new OrcidFixture("{$folder}/delete"),
        new OrcidFixture("{$folder}/get-deleted"),
    ]);

    // with known scopes, they are checked before sending: the token has them all
    $peerReviews = $this->getMemberApiClient(Scope::completeAccess())->withMockClient($mockClient)->peerReviews();

    // partly open: the reviewed article is named
    $putCode = $peerReviews->create(testPeerReview(
        'lifecycle',
        role: PeerReviewRole::Editor,
        reviewUrl: new Url('https://example.org/orcid-php-sdk/reviews/lifecycle'),
        completionDate: FuzzyDate::fromString('2024-11-05'),
        subjectExternalId: ExternalId::doi('10.5555/orcid-php-sdk-lifecycle'),
        subjectContainerName: 'Canadian Manuscript Report of Fisheries and Aquatic Sciences',
        subjectType: PeerReviewSubjectType::Report,
        subjectName: new Title('orcid-php-sdk reviewed report', translatedTitle: new TranslatedTitle('Rapport évalué', 'fr')),
        subjectUrl: new Url('https://example.org/orcid-php-sdk/reports/lifecycle'),
    ));

    expect($putCode)->toBeGreaterThan(0);

    $created = $peerReviews->get($putCode);

    expect($created->putCode)->toBe($putCode)
        ->and($created->role)->toBe(PeerReviewRole::Editor)
        ->and($created->rawRole)->toBe('editor')
        ->and($created->type)->toBe(PeerReviewType::Review)
        ->and($created->rawType)->toBe('review')
        ->and($created->reviewIdentifiers)->toHaveCount(1)
        ->and($created->reviewIdentifiers[0]->value)->toBe('orcid-php-sdk-lifecycle')
        ->and((string) $created->reviewUrl)->toBe('https://example.org/orcid-php-sdk/reviews/lifecycle')
        ->and((string) $created->completionDate)->toBe('2024-11-05')
        ->and($created->reviewGroupId)->toBe(TEST_REVIEW_GROUP)
        ->and($created->subjectExternalId?->type)->toBe('doi')
        ->and($created->subjectExternalId?->value)->toBe('10.5555/orcid-php-sdk-lifecycle')
        ->and($created->subjectExternalId?->url)->toBe('https://doi.org/10.5555/orcid-php-sdk-lifecycle')
        ->and($created->subjectContainerName)->toBe('Canadian Manuscript Report of Fisheries and Aquatic Sciences')
        ->and($created->subjectType)->toBe(PeerReviewSubjectType::Report)
        ->and($created->rawSubjectType)->toBe('report')
        ->and($created->subjectName?->title)->toBe('orcid-php-sdk reviewed report')
        ->and($created->subjectName?->translatedTitle?->title)->toBe('Rapport évalué')
        ->and((string) $created->subjectUrl)->toBe('https://example.org/orcid-php-sdk/reports/lifecycle')
        ->and($created->conveningOrganization?->name)->toBe('Fisheries and Oceans Canada')
        ->and($created->conveningOrganization?->disambiguatedOrganization?->identifier)->toBe('https://ror.org/02qa1x782')
        ->and($this->isOurs($created->source))->toBeTrue()
        ->and($created->createdAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($created->visibility)->toBe(Visibility::Public);

    // every connector reads public peer reviews through record($id)
    $public = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->peerReviews()->get($putCode);

    expect($public->subjectName?->title)->toBe('orcid-php-sdk reviewed report')
        ->and($this->isOurs($public->source))->toBeTrue();

    // PUT replaces the whole item: anonymous now, the subject fields left out are cleared
    $updated = $peerReviews->update($putCode, testPeerReview('lifecycle', type: PeerReviewType::Evaluation));

    expect($updated->putCode)->toBe($putCode)
        ->and($updated->type)->toBe(PeerReviewType::Evaluation)
        ->and($updated->role)->toBe(PeerReviewRole::Reviewer)
        ->and($updated->reviewUrl)->toBeNull()
        ->and($updated->subjectExternalId)->toBeNull()
        ->and($updated->subjectName)->toBeNull()
        ->and($updated->subjectType)->toBeNull()
        ->and((string) $updated->completionDate)->toBe('2025-03');

    // read before the cleanup, asserted after it, so a failed assertion never leaves the peer review behind
    try {
        $list = $peerReviews->list();
        $publicList = $this->getPublicApiClient()->withMockClient($mockClient)->record($this->orcidId())->peerReviews()->list();
    } finally {
        $peerReviews->delete($putCode);
    }

    // the fixture only keeps the review group and the group holding the test peer review
    expect($list->groups)->toHaveCount(1)
        ->and($list->groups[0]->reviewGroupId)->toBe(TEST_REVIEW_GROUP)
        ->and($list->groups[0]->externalIds[0]->value)->toBe('orcid-php-sdk-lifecycle')
        ->and($list->groups[0]->summaries)->toHaveCount(1)
        ->and(array_keys($list->byReviewGroup()))->toBe([TEST_REVIEW_GROUP])
        ->and($list->preferred())->toBe($list->summaries())
        ->and($list->summaries()[0]->putCode)->toBe($putCode)
        ->and($list->summaries()[0]->type)->toBe(PeerReviewType::Evaluation)
        ->and($list->summaries()[0]->role)->toBe(PeerReviewRole::Reviewer)
        ->and($list->summaries()[0]->reviewGroupId)->toBe(TEST_REVIEW_GROUP)
        ->and($list->summaries()[0]->reviewIdentifiers[0]->value)->toBe('orcid-php-sdk-lifecycle')
        ->and((string) $list->summaries()[0]->completionDate)->toBe('2025-03')
        ->and($list->summaries()[0]->conveningOrganization?->name)->toBe('Fisheries and Oceans Canada')
        ->and($list->summaries()[0]->displayIndex)->toBe(1)
        ->and($this->isOurs($list->summaries()[0]->source))->toBeTrue()
        ->and($publicList->summaries()[0]->putCode)->toBe($putCode)
        ->and(fn (): PeerReview => $peerReviews->get($putCode))->toThrow(OrcidNotFoundException::class);
});

it('refuses a review group that does not exist', function (): void {
    $mockClient = new MockClient([
        CreatePeerReview::class => new OrcidFixture('peer-reviews/unknown-group'),
    ]);

    $peerReviews = $this->getMemberApiClient()->withMockClient($mockClient)->peerReviews();

    // only an "issn:" group is created on first use
    try {
        $peerReviews->create(testPeerReview('unknown group', reviewGroupId: 'orcid-generated:orcid-php-sdk-unknown'));
        $error = null;
    } catch (OrcidValidationException $exception) {
        $error = $exception;
    }

    expect($error)->toBeInstanceOf(OrcidValidationException::class)
        ->and($error?->status())->toBe(400)
        ->and($error?->errorCode)->toBe(9020)
        ->and($error?->developerMessage)->toContain('group id does not exist');
});

it('checks the token scopes and the API before sending', function (): void {
    $mockClient = new MockClient([]);

    $readOnly = Orcid::member(accessToken: 'token', orcid: '0000-0002-1825-0097', scopes: [Scope::ReadLimited])->withMockClient($mockClient);

    expect(fn (): int => $readOnly->peerReviews()->create(testPeerReview('scope')))
        ->toThrow(MissingScopeException::class)
        ->and(fn (): PeerReview => $readOnly->peerReviews()->update(1, testPeerReview('scope')))
        ->toThrow(MissingScopeException::class)
        ->and(fn (): mixed => $readOnly->peerReviews()->delete(1))
        ->toThrow(MissingScopeException::class)
        ->and(fn (): Response => Orcid::public()->withMockClient($mockClient)->send(new CreatePeerReview('0000-0002-1825-0097', testPeerReview('public'))))
        ->toThrow(LogicException::class, 'requires the Member API')
        ->and(fn (): mixed => Orcid::member(accessToken: 'token')->peerReviews())
        ->toThrow(LogicException::class, 'bound to a record');

    $mockClient->assertNothingSent();
});
