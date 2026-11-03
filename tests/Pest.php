<?php

declare(strict_types=1);

use Tests\BaseTest;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Affiliations\AffiliationInput;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Fundings\FundingInput;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewInput;
use VincentAuger\OrcidSdk\Data\Person\AddressInput;
use VincentAuger\OrcidSdk\Data\Person\KeywordInput;
use VincentAuger\OrcidSdk\Data\Person\OtherNameInput;
use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifierInput;
use VincentAuger\OrcidSdk\Data\Person\ResearcherUrlInput;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceInput;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceItemInput;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceProposalInput;
use VincentAuger\OrcidSdk\Data\Works\WorkInput;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Enums\FundingType;
use VincentAuger\OrcidSdk\Enums\PeerReviewRole;
use VincentAuger\OrcidSdk\Enums\PeerReviewType;
use VincentAuger\OrcidSdk\Enums\ResearchResourceType;
use VincentAuger\OrcidSdk\Enums\WorkType;
use VincentAuger\OrcidSdk\Orcid;

pest()->extend(BaseTest::class);

/**
 * The DOI of the work added by hand on the sandbox record (issue #3): a test work with the same DOI is grouped with it
 */
const HAND_ADDED_DOI = '10.1016/j.pocean.2016.07.005';

/**
 * An affiliation for tests: its department name starts with the test marker, and it has its own
 * `self` external identifier, so ORCID never groups it with another test item.
 */
function testAffiliation(
    string $label,
    ?string $roleTitle = 'Tester',
    ?FuzzyDate $endDate = null,
    string $ror = 'https://ror.org/02qa1x782',
): AffiliationInput {
    return new AffiliationInput(
        organization: new Organization(
            'Fisheries and Oceans Canada',
            new OrganizationAddress('Ottawa', 'CA', 'ON'),
            DisambiguatedOrganization::ror($ror),
        ),
        departmentName: OrcidFixture::TEST_MARKER.' '.$label,
        roleTitle: $roleTitle,
        startDate: FuzzyDate::fromString('2021-01-05'),
        endDate: $endDate,
        externalIds: [ExternalId::grantNumber(OrcidFixture::TEST_MARKER.'-'.str_replace(' ', '-', $label))],
    );
}

/**
 * A funding for tests: its title starts with the test marker, and it has its own `self` external identifier, so
 * ORCID never groups it with another funding.
 */
function testFunding(string $label, mixed ...$fields): FundingInput
{
    return new FundingInput(...[
        'title' => new Title(OrcidFixture::TEST_MARKER.' '.$label),
        'type' => FundingType::Grant,
        'organization' => new Organization(
            'Fisheries and Oceans Canada',
            new OrganizationAddress('Ottawa', 'CA', 'ON'),
            DisambiguatedOrganization::ror('https://ror.org/02qa1x782'),
        ),
        'externalIds' => [ExternalId::grantNumber(OrcidFixture::TEST_MARKER.'-'.str_replace(' ', '-', $label))],
        ...$fields,
    ]);
}

/**
 * DFO's Canadian manuscript reports of fisheries and aquatic sciences (online): the review group of test peer reviews
 */
const TEST_REVIEW_GROUP = 'issn:1488-5387';

/**
 * An anonymous peer review for tests: its review identifier starts with the test marker (a summary has no subject
 * name to mark) and is its own `self` identifier, so ORCID never groups it with another peer review.
 */
function testPeerReview(string $label, mixed ...$fields): PeerReviewInput
{
    return new PeerReviewInput(...[
        'role' => PeerReviewRole::Reviewer,
        'type' => PeerReviewType::Review,
        'reviewIdentifiers' => [ExternalId::sourceWorkId(OrcidFixture::TEST_MARKER.'-'.str_replace(' ', '-', $label))],
        'completionDate' => FuzzyDate::fromString('2025-03'),
        'reviewGroupId' => TEST_REVIEW_GROUP,
        'conveningOrganization' => new Organization(
            'Fisheries and Oceans Canada',
            new OrganizationAddress('Ottawa', 'CA', 'ON'),
            DisambiguatedOrganization::ror('https://ror.org/02qa1x782'),
        ),
        ...$fields,
    ]);
}

/**
 * A research resource for tests: its proposal title starts with the test marker, and the proposal has its own `self`
 * external identifier, so ORCID never groups it with another research resource. Fields change the proposal; pass
 * `items` to change the resource items.
 *
 * @param  list<ResearchResourceItemInput>|null  $items
 */
function testResearchResource(string $label, ?array $items = null, mixed ...$fields): ResearchResourceInput
{
    $slug = OrcidFixture::TEST_MARKER.'-'.str_replace(' ', '-', $label);
    $host = new Organization(
        'Fisheries and Oceans Canada',
        new OrganizationAddress('Ottawa', 'CA', 'ON'),
        DisambiguatedOrganization::ror('https://ror.org/02qa1x782'),
    );

    return new ResearchResourceInput(
        new ResearchResourceProposalInput(...[
            'title' => new Title(OrcidFixture::TEST_MARKER.' '.$label),
            'hosts' => [$host],
            'externalIds' => [ExternalId::proposalId($slug)],
            ...$fields,
        ]),
        $items ?? [new ResearchResourceItemInput(
            OrcidFixture::TEST_MARKER.' vessel',
            ResearchResourceType::Infrastructures,
            [$host],
            [ExternalId::sourceWorkId($slug.'-vessel')],
        )],
    );
}

/**
 * A work for tests: its title starts with the test marker, and it has its own `self` external identifier, so
 * ORCID never groups it with another work.
 */
function testWork(string $label, mixed ...$fields): WorkInput
{
    return new WorkInput(...[
        'title' => new Title(OrcidFixture::TEST_MARKER.' '.$label),
        'type' => WorkType::JournalArticle,
        'externalIds' => [new ExternalId('other-id', OrcidFixture::TEST_MARKER.'-'.str_replace(' ', '-', $label))],
        ...$fields,
    ]);
}

/**
 * The fixtures of withTestActivities(), in the order it sends them, around the fixtures of the read in between
 *
 * @return list<OrcidFixture>
 */
function testActivityFixtures(string $folder, OrcidFixture ...$read): array
{
    $sections = ['work', 'funding', 'peer-review', 'research-resource', 'service'];

    return [
        ...array_map(fn (string $section): OrcidFixture => new OrcidFixture("{$folder}/create-{$section}"), $sections),
        ...array_values($read),
        ...array_map(fn (string $section): OrcidFixture => new OrcidFixture("{$folder}/delete-{$section}"), $sections),
    ];
}

/**
 * Add one test item to the works, fundings, peer reviews, research resources and services of the record, run the
 * read, then delete them all (even when the read fails)
 *
 * @template T
 *
 * @param  callable(array{work: int, funding: int, peerReview: int, researchResource: int, service: int}): T  $read
 * @return T
 */
function withTestActivities(Orcid $owner, string $label, callable $read): mixed
{
    $putCodes = [
        'work' => $owner->works()->create(testWork($label)),
        'funding' => $owner->fundings()->create(testFunding($label)),
        'peerReview' => $owner->peerReviews()->create(testPeerReview($label)),
        'researchResource' => $owner->researchResources()->create(testResearchResource($label)),
        'service' => $owner->affiliations()->create(AffiliationType::Service, testAffiliation($label)),
    ];

    try {
        return $read($putCodes);
    } finally {
        $owner->works()->delete($putCodes['work']);
        $owner->fundings()->delete($putCodes['funding']);
        $owner->peerReviews()->delete($putCodes['peerReview']);
        $owner->researchResources()->delete($putCodes['researchResource']);
        $owner->affiliations()->delete(AffiliationType::Service, $putCodes['service']);
    }
}

/**
 * The fixtures of withTestPersonItems(), in the order it sends them, around the read in between
 *
 * @return list<OrcidFixture>
 */
function testPersonItemFixtures(string $folder, string $read): array
{
    $sections = ['keyword', 'other-name', 'researcher-url', 'external-identifier', 'address'];

    return [
        ...array_map(fn (string $section): OrcidFixture => new OrcidFixture("{$folder}/create-{$section}"), $sections),
        new OrcidFixture("{$folder}/{$read}"),
        ...array_map(fn (string $section): OrcidFixture => new OrcidFixture("{$folder}/delete-{$section}"), $sections),
    ];
}

/**
 * Add one test item to every writable person section of the record, run the read, then delete them all (even when
 * the read fails). Test addresses use Antarctica (AQ): an address has only a country, nothing to mark.
 *
 * @template T
 *
 * @param  callable(array{keyword: int, otherName: int, researcherUrl: int, externalIdentifier: int, address: int}): T  $read
 * @return T
 */
function withTestPersonItems(Orcid $owner, string $label, callable $read): mixed
{
    $marked = OrcidFixture::TEST_MARKER.' '.$label;
    $slug = OrcidFixture::TEST_MARKER.'-'.str_replace(' ', '-', $label);
    $putCodes = [
        'keyword' => $owner->keywords()->create(new KeywordInput($marked)),
        'otherName' => $owner->otherNames()->create(new OtherNameInput($marked)),
        'researcherUrl' => $owner->researcherUrls()->create(new ResearcherUrlInput('https://example.org/'.$slug, $marked)),
        'externalIdentifier' => $owner->externalIdentifiers()->create(new PersonExternalIdentifierInput('Scopus Author ID', $slug, 'https://example.org/'.$slug)),
        'address' => $owner->addresses()->create(new AddressInput('AQ')),
    ];

    try {
        return $read($putCodes);
    } finally {
        $owner->keywords()->delete($putCodes['keyword']);
        $owner->otherNames()->delete($putCodes['otherName']);
        $owner->researcherUrls()->delete($putCodes['researcherUrl']);
        $owner->externalIdentifiers()->delete($putCodes['externalIdentifier']);
        $owner->addresses()->delete($putCodes['address']);
    }
}
