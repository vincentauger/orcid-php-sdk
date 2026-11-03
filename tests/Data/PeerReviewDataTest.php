<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\TranslatedTitle;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReview;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewGroups;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewInput;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewSummary;
use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Enums\FundingType;
use VincentAuger\OrcidSdk\Enums\PeerReviewRole;
use VincentAuger\OrcidSdk\Enums\PeerReviewSubjectType;
use VincentAuger\OrcidSdk\Enums\PeerReviewType;
use VincentAuger\OrcidSdk\Enums\WorkType;

function convener(): Organization
{
    return new Organization('Canadian Science Publishing', new OrganizationAddress('Ottawa', 'CA'));
}

/**
 * A valid peer review, with the given fields changed
 */
function peerReview(mixed ...$fields): PeerReviewInput
{
    return new PeerReviewInput(...[
        'role' => PeerReviewRole::Reviewer,
        'type' => PeerReviewType::Review,
        'reviewIdentifiers' => [ExternalId::sourceWorkId('review-1234')],
        'completionDate' => FuzzyDate::fromString('2025-03'),
        'reviewGroupId' => 'issn:0706-652X',
        'conveningOrganization' => convener(),
        ...$fields,
    ]);
}

describe('PeerReviewInput', function (): void {
    it('sends a minimal peer review', function (): void {
        $input = new PeerReviewInput(
            role: PeerReviewRole::Reviewer,
            type: PeerReviewType::Review,
            reviewIdentifiers: [ExternalId::sourceWorkId('review-1234')],
            completionDate: FuzzyDate::fromString('2025-03'),
            reviewGroupId: 'issn:0706-652X',
            conveningOrganization: convener(),
        );

        expect($input->toArray())->toBe([
            'reviewer-role' => 'reviewer',
            'review-identifiers' => ['external-id' => [[
                'external-id-type' => 'source-work-id',
                'external-id-value' => 'review-1234',
                'external-id-relationship' => 'self',
            ]]],
            'review-type' => 'review',
            'review-completion-date' => ['year' => ['value' => '2025'], 'month' => ['value' => '03']],
            'review-group-id' => 'issn:0706-652X',
            'convening-organization' => [
                'name' => 'Canadian Science Publishing',
                'address' => ['city' => 'Ottawa', 'country' => 'CA'],
            ],
        ]);
    });

    it('sends every field, in ORCID schema order', function (): void {
        $input = new PeerReviewInput(
            role: PeerReviewRole::Editor,
            type: PeerReviewType::Evaluation,
            reviewIdentifiers: [ExternalId::sourceWorkId('review-1234', url: 'https://example.org/reviews/1234')],
            completionDate: FuzzyDate::fromString('2024-11-05'),
            reviewGroupId: 'issn:0706-652X',
            conveningOrganization: new Organization('Canadian Science Publishing', new OrganizationAddress('Ottawa', 'CA', 'ON'), DisambiguatedOrganization::ror('https://ror.org/03xm8nn72')),
            reviewUrl: new Url('https://example.org/reviews/1234'),
            subjectExternalId: ExternalId::doi('10.1139/cjfas-2024-0001'),
            subjectContainerName: 'Canadian Journal of Fisheries and Aquatic Sciences',
            subjectType: PeerReviewSubjectType::JournalArticle,
            subjectName: new Title('Salmon in a warming ocean', translatedTitle: new TranslatedTitle('Le saumon dans un océan qui se réchauffe', 'fr')),
            subjectUrl: new Url('https://doi.org/10.1139/cjfas-2024-0001'),
        );

        expect($input->toArray(42))->toBe([
            'put-code' => 42,
            'reviewer-role' => 'editor',
            'review-identifiers' => ['external-id' => [[
                'external-id-type' => 'source-work-id',
                'external-id-value' => 'review-1234',
                'external-id-url' => ['value' => 'https://example.org/reviews/1234'],
                'external-id-relationship' => 'self',
            ]]],
            'review-url' => ['value' => 'https://example.org/reviews/1234'],
            'review-type' => 'evaluation',
            'review-completion-date' => ['year' => ['value' => '2024'], 'month' => ['value' => '11'], 'day' => ['value' => '05']],
            'review-group-id' => 'issn:0706-652X',
            'subject-external-identifier' => [
                'external-id-type' => 'doi',
                'external-id-value' => '10.1139/cjfas-2024-0001',
                'external-id-url' => ['value' => 'https://doi.org/10.1139/cjfas-2024-0001'],
                'external-id-relationship' => 'self',
            ],
            'subject-container-name' => ['value' => 'Canadian Journal of Fisheries and Aquatic Sciences'],
            'subject-type' => 'journal-article',
            'subject-name' => [
                'title' => ['value' => 'Salmon in a warming ocean'],
                'translated-title' => ['value' => 'Le saumon dans un océan qui se réchauffe', 'language-code' => 'fr'],
            ],
            'subject-url' => ['value' => 'https://doi.org/10.1139/cjfas-2024-0001'],
            'convening-organization' => [
                'name' => 'Canadian Science Publishing',
                'address' => ['city' => 'Ottawa', 'region' => 'ON', 'country' => 'CA'],
                'disambiguated-organization' => ['disambiguated-organization-identifier' => 'https://ror.org/03xm8nn72', 'disambiguation-source' => 'ROR'],
            ],
        ]);
    });
    it('refuses what ORCID would reject', function (Closure $input, string $message): void {
        expect($input)->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'no review identifier' => [fn (): PeerReviewInput => peerReview(reviewIdentifiers: []), 'review identifier'],
        'a blank organization name' => [fn (): PeerReviewInput => peerReview(conveningOrganization: new Organization(' ', new OrganizationAddress('Ottawa', 'CA'))), 'organization name'],
        'no organization address' => [fn (): PeerReviewInput => peerReview(conveningOrganization: new Organization('Canadian Science Publishing')), 'organization address'],
        'an empty organization address' => [fn (): PeerReviewInput => peerReview(conveningOrganization: new Organization('Canadian Science Publishing', new OrganizationAddress(' ', null, 'ON'))), 'organization address'],
        'a group id without a known prefix' => [fn (): PeerReviewInput => peerReview(reviewGroupId: '0706-652X'), 'Invalid review group id'],
        'a group id with a blank value' => [fn (): PeerReviewInput => peerReview(reviewGroupId: 'orcid-generated:'), 'Invalid review group id'],
        'an ISSN without its hyphen' => [fn (): PeerReviewInput => peerReview(reviewGroupId: 'issn:0706652X'), 'Invalid ISSN'],
        'an ISSN with a wrong check digit' => [fn (): PeerReviewInput => peerReview(reviewGroupId: 'issn:0706-6521'), 'Invalid ISSN'],
        'a placeholder ISSN' => [fn (): PeerReviewInput => peerReview(reviewGroupId: 'issn:1488-XXXE'), 'Invalid ISSN'],
        'a subject subtitle' => [fn (): PeerReviewInput => peerReview(subjectName: new Title('Salmon', 'in a warming ocean')), 'subtitle'],
        'a long subject title' => [fn (): PeerReviewInput => peerReview(subjectName: new Title(str_repeat('a', 1001))), 'subject title'],
        'a long translated subject title' => [fn (): PeerReviewInput => peerReview(subjectName: new Title('Salmon', translatedTitle: new TranslatedTitle(str_repeat('a', 1001), 'fr'))), 'translated subject title'],
        'an invalid language code' => [fn (): PeerReviewInput => peerReview(subjectName: new Title('Salmon', translatedTitle: new TranslatedTitle('Saumon', 'french'))), 'language code'],
        'a long container name' => [fn (): PeerReviewInput => peerReview(subjectContainerName: str_repeat('a', 1001)), 'container name'],
    ]);

    it('accepts what ORCID accepts', function (Closure $input): void {
        expect($input())->toBeInstanceOf(PeerReviewInput::class);
    })->with([
        // c3/r04: unlike a work, any relationship will do
        'a part-of review identifier only' => [fn (): PeerReviewInput => peerReview(reviewIdentifiers: [ExternalId::sourceWorkId('review-1234', ExternalIdRelationship::PartOf)])],
        // c3/r09, c3/r10
        'an organization with a country only and no disambiguated organization' => [fn (): PeerReviewInput => peerReview(conveningOrganization: new Organization('Canadian Science Publishing', new OrganizationAddress(null, 'CA')))],
        // c3/r29: stored as issn:0706-652X
        'a lowercase check digit' => [fn (): PeerReviewInput => peerReview(reviewGroupId: 'issn:0706-652x')],
        // c3/r28: ORCID checks the check digit, not that the journal exists
        'an ISSN no journal has' => [fn (): PeerReviewInput => peerReview(reviewGroupId: 'issn:0000-0019')],
        'another group type' => [fn (): PeerReviewInput => peerReview(reviewGroupId: 'orcid-generated:my-conference-2025')],
        'a partial completion date' => [fn (): PeerReviewInput => peerReview(completionDate: FuzzyDate::fromString('2025'))],
    ]);

    it('treats a blank container name as not set', function (): void {
        expect(peerReview(subjectContainerName: ' ')->toArray())->not->toHaveKey('subject-container-name');
    });
});

describe('PeerReviewSubjectType', function (): void {
    it('takes every work and funding type', function (): void {
        $values = array_column(PeerReviewSubjectType::cases(), 'value');

        expect(array_diff(array_column([...WorkType::cases(), ...FundingType::cases()], 'value'), $values))->toBe([])
            ->and(array_values(array_diff($values, array_column([...WorkType::cases(), ...FundingType::cases()], 'value'))))->toBe(['research-resource-proposal']);
    });
});

describe('PeerReview', function (): void {
    it('keeps values unknown to the SDK', function (): void {
        $peerReview = PeerReview::fromArray([
            'put-code' => 42,
            'reviewer-role' => 'referee',
            'review-type' => 'opinion',
            'subject-type' => 'undefined',
            'subject-external-identifier' => null,
            'subject-name' => ['title' => null],
            'visibility' => 'public',
        ]);

        expect($peerReview->putCode)->toBe(42)
            ->and($peerReview->role)->toBeNull()
            ->and($peerReview->rawRole)->toBe('referee')
            ->and($peerReview->type)->toBeNull()
            ->and($peerReview->rawType)->toBe('opinion')
            ->and($peerReview->subjectType)->toBeNull()
            ->and($peerReview->rawSubjectType)->toBe('undefined')
            ->and($peerReview->subjectExternalId)->toBeNull()
            ->and($peerReview->subjectName)->toBeNull()
            ->and($peerReview->reviewIdentifiers)->toBe([])
            ->and($peerReview->conveningOrganization)->toBeNull()
            ->and($peerReview->completionDate)->toBeNull();
    });

    it('reads the enums it knows', function (): void {
        $peerReview = PeerReview::fromArray([
            'put-code' => 42,
            'reviewer-role' => 'chair',
            'review-type' => 'evaluation',
            'subject-type' => 'research-resource-proposal',
        ]);

        expect($peerReview->role)->toBe(PeerReviewRole::Chair)
            ->and($peerReview->type)->toBe(PeerReviewType::Evaluation)
            ->and($peerReview->subjectType)->toBe(PeerReviewSubjectType::ResearchResourceProposal);
    });
});

describe('PeerReviewGroups', function (): void {
    // hand-built: the sandbox only holds peer reviews from our client, each in its own group
    it('keeps each group in its review group and prefers the highest display index', function (): void {
        $reviewGroup = fn (string $id, array $groups): array => [
            'external-ids' => ['external-id' => [['external-id-type' => 'peer-review', 'external-id-value' => $id]]],
            'peer-review-group' => $groups,
        ];

        $list = PeerReviewGroups::fromArray(['group' => [
            $reviewGroup('issn:0706-652X', [
                ['external-ids' => ['external-id' => [['external-id-type' => 'source-work-id', 'external-id-value' => 'R-1']]], 'peer-review-summary' => [
                    ['put-code' => 1, 'display-index' => '0', 'reviewer-role' => 'referee'],
                    ['put-code' => 2, 'display-index' => '3'],
                    ['put-code' => 3, 'display-index' => '1'],
                ]],
                ['external-ids' => ['external-id' => []], 'peer-review-summary' => [['put-code' => 4, 'display-index' => '0'], ['put-code' => 5, 'display-index' => '0']]],
            ]),
            $reviewGroup('issn:1488-5387', [['peer-review-summary' => [['put-code' => 6]]], ['peer-review-summary' => []]]),
            // a review group without its id: the summaries' own
            ['peer-review-group' => [['peer-review-summary' => [['put-code' => 7, 'review-group-id' => 'issn:0953-1513']]]]],
        ]]);

        $putCodes = fn (array $summaries): array => array_map(fn (PeerReviewSummary $summary): int => $summary->putCode, $summaries);

        expect($list->groups)->toHaveCount(4)
            ->and($list->groups[0]->reviewGroupId)->toBe('issn:0706-652X')
            ->and($list->groups[0]->externalIds[0]->value)->toBe('R-1')
            ->and($list->groups[1]->externalIds)->toBe([])
            ->and($list->groups[2]->reviewGroupId)->toBe('issn:1488-5387')
            ->and($list->groups[3]->reviewGroupId)->toBe('issn:0953-1513')
            ->and(array_map(count(...), $list->byReviewGroup()))->toBe(['issn:0706-652X' => 2, 'issn:1488-5387' => 1, 'issn:0953-1513' => 1])
            ->and($putCodes($list->preferred()))->toBe([2, 4, 6, 7])
            ->and($putCodes($list->summaries()))->toBe([1, 2, 3, 4, 5, 6, 7])
            ->and($list->groups[0]->summaries[0]->role)->toBeNull()
            ->and($list->groups[0]->summaries[0]->rawRole)->toBe('referee');
    });

    it('reads a record without peer reviews', function (): void {
        $list = PeerReviewGroups::fromArray(['last-modified-date' => null, 'group' => [], 'path' => '/0000-0002-1825-0097/peer-reviews']);

        expect($list->groups)->toBe([])
            ->and($list->byReviewGroup())->toBe([])
            ->and($list->preferred())->toBe([])
            ->and($list->summaries())->toBe([]);
    });
});
