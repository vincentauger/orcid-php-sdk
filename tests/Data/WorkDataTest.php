<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Common\Contributor;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\TranslatedTitle;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Works\Citation;
use VincentAuger\OrcidSdk\Data\Works\Work;
use VincentAuger\OrcidSdk\Data\Works\WorkGroups;
use VincentAuger\OrcidSdk\Data\Works\WorkInput;
use VincentAuger\OrcidSdk\Data\Works\WorkSummary;
use VincentAuger\OrcidSdk\Enums\CitationType;
use VincentAuger\OrcidSdk\Enums\ContributorRole;
use VincentAuger\OrcidSdk\Enums\ContributorSequence;
use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Enums\WorkType;

/**
 * A valid work, with the given fields changed
 */
function work(mixed ...$fields): WorkInput
{
    return new WorkInput(...[
        'title' => new Title('Ocean acidification'),
        'type' => WorkType::JournalArticle,
        'externalIds' => [new ExternalId('doi', '10.5555/abc')],
        ...$fields,
    ]);
}

describe('WorkInput', function (): void {
    it('sends a minimal work', function (): void {
        $input = new WorkInput(
            title: new Title('Ocean acidification'),
            type: WorkType::JournalArticle,
            externalIds: [new ExternalId('doi', '10.5555/abc')],
        );

        expect($input->toArray())->toBe([
            'title' => ['title' => ['value' => 'Ocean acidification']],
            'type' => 'journal-article',
            'external-ids' => ['external-id' => [[
                'external-id-type' => 'doi',
                'external-id-value' => '10.5555/abc',
                'external-id-relationship' => 'self',
            ]]],
        ]);
    });

    it('requires a self external identifier', function (array $externalIds): void {
        new WorkInput(new Title('Ocean acidification'), WorkType::JournalArticle, $externalIds);
    })->with([
        'none' => [[]],
        'only part-of' => [[new ExternalId('issn', '1234-5679', ExternalIdRelationship::PartOf)]],
        'only version-of' => [[new ExternalId('doi', '10.5555/abc', ExternalIdRelationship::VersionOf)]],
    ])->throws(InvalidArgumentException::class, "relationship 'self'");

    it('sends every field, dates in the {value} form', function (): void {
        $input = new WorkInput(
            title: new Title('Ocean acidification', 'A review', new TranslatedTitle('Acidification des océans', 'fr')),
            type: WorkType::JournalArticle,
            externalIds: [
                new ExternalId('doi', '10.5555/abc', url: 'https://doi.org/10.5555/abc'),
                new ExternalId('issn', '1234-5679', ExternalIdRelationship::PartOf),
            ],
            journalTitle: 'Journal of Oceans',
            shortDescription: 'What happens to the oceans',
            citation: new Citation(CitationType::Bibtex, '@article{a, title={Ocean acidification}}'),
            publicationDate: FuzzyDate::fromString('2021-03'),
            url: new Url('https://example.org/abc'),
            contributors: [
                new Contributor(creditName: 'Josiah Carberry', orcid: 'https://orcid.org/0000-0002-1825-0097', role: ContributorRole::Author, sequence: ContributorSequence::First),
                new Contributor(creditName: 'Someone Else', role: ContributorRole::Software),
            ],
            languageCode: 'en',
            country: 'ca',
        );

        expect($input->toArray(1234))->toBe([
            'put-code' => 1234,
            'title' => [
                'title' => ['value' => 'Ocean acidification'],
                'subtitle' => ['value' => 'A review'],
                'translated-title' => ['value' => 'Acidification des océans', 'language-code' => 'fr'],
            ],
            'journal-title' => ['value' => 'Journal of Oceans'],
            'short-description' => 'What happens to the oceans',
            'citation' => ['citation-type' => 'bibtex', 'citation-value' => '@article{a, title={Ocean acidification}}'],
            'type' => 'journal-article',
            'publication-date' => ['year' => ['value' => '2021'], 'month' => ['value' => '03']],
            'external-ids' => ['external-id' => [
                [
                    'external-id-type' => 'doi',
                    'external-id-value' => '10.5555/abc',
                    'external-id-url' => ['value' => 'https://doi.org/10.5555/abc'],
                    'external-id-relationship' => 'self',
                ],
                [
                    'external-id-type' => 'issn',
                    'external-id-value' => '1234-5679',
                    'external-id-relationship' => 'part-of',
                ],
            ]],
            'url' => ['value' => 'https://example.org/abc'],
            'contributors' => ['contributor' => [
                [
                    'contributor-orcid' => ['path' => '0000-0002-1825-0097'],
                    'credit-name' => ['value' => 'Josiah Carberry'],
                    'contributor-attributes' => ['contributor-sequence' => 'first', 'contributor-role' => 'author'],
                ],
                [
                    'credit-name' => ['value' => 'Someone Else'],
                    'contributor-attributes' => ['contributor-role' => 'http://credit.niso.org/contributor-roles/software/'],
                ],
            ]],
            'language-code' => 'en',
            'country' => ['value' => 'CA'],
        ]);
    });

    it('refuses what ORCID would reject, before sending', function (Closure $build, string $message): void {
        expect($build)->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'blank title (9047)' => [fn (): Title => new Title(' '), 'title cannot be blank'],
        'title over 1000 characters (9047)' => [fn (): WorkInput => work(title: new Title(str_repeat('x', 1001))), 'title is longer than 1000'],
        'subtitle over 1000 characters' => [fn (): WorkInput => work(title: new Title('Ocean', str_repeat('x', 1001))), 'subtitle is longer than 1000'],
        'translated title over 1000 characters' => [fn (): WorkInput => work(title: new Title('Ocean', translatedTitle: new TranslatedTitle(str_repeat('é', 1001), 'fr'))), 'translated title is longer than 1000'],
        'translated title without a language (9047)' => [fn (): TranslatedTitle => new TranslatedTitle('Océan', ''), 'needs a language code'],
        'translated title with a malformed language (9047)' => [fn (): WorkInput => work(title: new Title('Ocean', translatedTitle: new TranslatedTitle('Océan', 'french'))), 'language code'],
        'journal title over 1000 characters' => [fn (): WorkInput => work(journalTitle: str_repeat('x', 1001)), 'journal title is longer than 1000'],
        'short description over 5000 characters' => [fn (): WorkInput => work(shortDescription: str_repeat('x', 5001)), 'short description is longer than 5000'],
        'blank citation (9020)' => [fn (): Citation => new Citation(CitationType::Bibtex, ' '), 'citation cannot be blank'],
        'citation of an unknown type (9047)' => [fn (): WorkInput => work(citation: new Citation(null, 'x', 'nope')), 'citation type'],
        'contributor role unknown to ORCID (9058)' => [fn (): WorkInput => work(contributors: [new Contributor('A', rawRole: 'boss')]), 'contributor role'],
        'contributor sequence unknown to ORCID' => [fn (): WorkInput => work(contributors: [new Contributor('A', rawSequence: 'last')]), 'contributor sequence'],
        'contributor with an invalid iD' => [fn (): Contributor => new Contributor('A', '0000-0002-1825-0098'), 'bad checksum'],
        'contributor credit name over 150 characters' => [fn (): WorkInput => work(contributors: [new Contributor(str_repeat('x', 151))]), 'credit name is longer than 150'],
        'malformed language code (9047)' => [fn (): WorkInput => work(languageCode: 'english'), 'language code'],
        'malformed country (9047)' => [fn (): WorkInput => work(country: 'CAN'), 'country'],
    ]);

    it('leaves out blank optional strings', function (): void {
        $input = new WorkInput(
            title: new Title(' Ocean acidification ', subtitle: '  '),
            type: WorkType::Software,
            externalIds: [new ExternalId('other-id', 'abc')],
            journalTitle: '',
            shortDescription: ' ',
            languageCode: '',
            country: ' ',
        );

        expect($input->toArray())->toBe([
            'title' => ['title' => ['value' => 'Ocean acidification']],
            'type' => 'software',
            'external-ids' => ['external-id' => [['external-id-type' => 'other-id', 'external-id-value' => 'abc', 'external-id-relationship' => 'self']]],
        ]);
    });
});

describe('Work', function (): void {
    it('keeps values unknown to the SDK', function (): void {
        $work = Work::fromArray([
            'put-code' => 42,
            'title' => ['title' => ['value' => 'Ocean acidification'], 'subtitle' => null, 'translated-title' => null],
            'type' => 'hologram',
            'citation' => ['citation-type' => 'formatted-new-style', 'citation-value' => 'Carberry, J. (2021)'],
            'contributors' => ['contributor' => [[
                'contributor-orcid' => ['path' => 'not-an-orcid'],
                'credit-name' => ['value' => 'Josiah Carberry'],
                'contributor-attributes' => ['contributor-sequence' => 'last', 'contributor-role' => 'http://credit.niso.org/contributor-roles/new-role/'],
            ]]],
            'short-description' => '',
            'visibility' => 'public',
        ]);

        expect($work->putCode)->toBe(42)
            ->and($work->type)->toBeNull()
            ->and($work->rawType)->toBe('hologram')
            ->and($work->citation?->type)->toBeNull()
            ->and($work->citation?->rawType)->toBe('formatted-new-style')
            ->and($work->contributors[0]->orcid)->toBeNull()
            ->and($work->contributors[0]->creditName)->toBe('Josiah Carberry')
            ->and($work->contributors[0]->role)->toBeNull()
            ->and($work->contributors[0]->rawRole)->toBe('http://credit.niso.org/contributor-roles/new-role/')
            ->and($work->contributors[0]->sequence)->toBeNull()
            ->and($work->contributors[0]->rawSequence)->toBe('last')
            ->and($work->shortDescription)->toBeNull()
            ->and($work->externalIds)->toBe([])
            ->and($work->publicationDate)->toBeNull();
    });

    it('reads the enums it knows', function (): void {
        $work = Work::fromArray(['put-code' => 42, 'type' => 'data-set', 'citation' => ['citation-type' => 'bibtex', 'citation-value' => '@misc{a}']]);

        expect($work->type)->toBe(WorkType::DataSet)
            ->and($work->rawType)->toBe('data-set')
            ->and($work->title)->toBeNull()
            ->and($work->citation?->type)->toBe(CitationType::Bibtex);
    });
});

describe('WorkGroups', function (): void {
    // hand-built: the sandbox can't put the preferred summary anywhere but first on demand (the researcher
    // chooses it on the ORCID website)
    it('prefers the highest display index, the first one listed on a tie', function (): void {
        $list = WorkGroups::fromArray(['group' => [
            ['work-summary' => [
                ['put-code' => 1, 'display-index' => '0', 'type' => 'hologram'],
                ['put-code' => 2, 'display-index' => '3'],
                ['put-code' => 3, 'display-index' => '1'],
            ]],
            ['work-summary' => [['put-code' => 4, 'display-index' => '0'], ['put-code' => 5, 'display-index' => '0']]],
            ['work-summary' => []],
        ]]);

        expect($list->groups)->toHaveCount(2)
            ->and(array_map(fn (WorkSummary $summary): int => $summary->putCode, $list->preferred()))->toBe([2, 4])
            ->and(array_map(fn (WorkSummary $summary): int => $summary->putCode, $list->summaries()))->toBe([1, 2, 3, 4, 5])
            ->and($list->groups[0]->summaries[0]->type)->toBeNull()
            ->and($list->groups[0]->summaries[0]->rawType)->toBe('hologram');
    });

    it('reads a record without works', function (): void {
        $list = WorkGroups::fromArray(['last-modified-date' => null, 'group' => [], 'path' => '/0000-0002-1825-0097/works']);

        expect($list->groups)->toBe([])
            ->and($list->preferred())->toBe([])
            ->and($list->summaries())->toBe([]);
    });
});
