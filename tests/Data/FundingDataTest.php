<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Common\Amount;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\TranslatedTitle;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Fundings\Funding;
use VincentAuger\OrcidSdk\Data\Fundings\FundingContributor;
use VincentAuger\OrcidSdk\Data\Fundings\FundingGroups;
use VincentAuger\OrcidSdk\Data\Fundings\FundingInput;
use VincentAuger\OrcidSdk\Data\Fundings\FundingSummary;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Enums\FundingContributorRole;
use VincentAuger\OrcidSdk\Enums\FundingType;

function funder(): Organization
{
    return new Organization('Natural Sciences and Engineering Research Council', new OrganizationAddress('Ottawa', 'CA'), DisambiguatedOrganization::ror('https://ror.org/01h531d29'));
}

/**
 * A valid funding, with the given fields changed
 */
function funding(mixed ...$fields): FundingInput
{
    return new FundingInput(...[
        'title' => new Title('Ocean acidification'),
        'type' => FundingType::Grant,
        'organization' => funder(),
        'externalIds' => [new ExternalId('grant_number', 'RGPIN-2021-1234')],
        ...$fields,
    ]);
}

describe('FundingInput', function (): void {
    it('sends a minimal funding', function (): void {
        $input = new FundingInput(
            title: new Title('Ocean acidification'),
            type: FundingType::Grant,
            organization: funder(),
            externalIds: [new ExternalId('grant_number', 'RGPIN-2021-1234')],
        );

        expect($input->toArray())->toBe([
            'type' => 'grant',
            'title' => ['title' => ['value' => 'Ocean acidification']],
            'external-ids' => ['external-id' => [[
                'external-id-type' => 'grant_number',
                'external-id-value' => 'RGPIN-2021-1234',
                'external-id-relationship' => 'self',
            ]]],
            'organization' => [
                'name' => 'Natural Sciences and Engineering Research Council',
                'address' => ['city' => 'Ottawa', 'country' => 'CA'],
                'disambiguated-organization' => ['disambiguated-organization-identifier' => 'https://ror.org/01h531d29', 'disambiguation-source' => 'ROR'],
            ],
        ]);
    });

    it('sends every field, in ORCID schema order', function (): void {
        $input = new FundingInput(
            title: new Title('Ocean acidification', translatedTitle: new TranslatedTitle('Acidification des océans', 'fr')),
            type: FundingType::Grant,
            organization: funder(),
            externalIds: [
                new ExternalId('grant_number', 'RGPIN-2021-1234', url: 'https://example.org/grants/1234'),
                new ExternalId('grant_number', 'RGPIN-2021', ExternalIdRelationship::PartOf),
            ],
            organizationDefinedType: 'Discovery grant',
            shortDescription: 'What happens to the oceans',
            amount: new Amount('125000.50', 'cad'),
            url: new Url('https://example.org/grants/1234'),
            startDate: FuzzyDate::fromString('2021-01-05'),
            endDate: FuzzyDate::fromString('2024-12'),
            contributors: [
                new FundingContributor('Josiah Carberry', 'https://orcid.org/0000-0002-1825-0097', FundingContributorRole::Lead),
                new FundingContributor('Someone Else', role: FundingContributorRole::FundingAcquisition),
                new FundingContributor('No Role'),
            ],
        );

        expect($input->toArray(1234))->toBe([
            'put-code' => 1234,
            'type' => 'grant',
            'organization-defined-type' => ['value' => 'Discovery grant'],
            'title' => [
                'title' => ['value' => 'Ocean acidification'],
                'translated-title' => ['value' => 'Acidification des océans', 'language-code' => 'fr'],
            ],
            'short-description' => 'What happens to the oceans',
            'amount' => ['value' => '125000.50', 'currency-code' => 'CAD'],
            'url' => ['value' => 'https://example.org/grants/1234'],
            'start-date' => ['year' => ['value' => '2021'], 'month' => ['value' => '01'], 'day' => ['value' => '05']],
            'end-date' => ['year' => ['value' => '2024'], 'month' => ['value' => '12']],
            'external-ids' => ['external-id' => [
                [
                    'external-id-type' => 'grant_number',
                    'external-id-value' => 'RGPIN-2021-1234',
                    'external-id-url' => ['value' => 'https://example.org/grants/1234'],
                    'external-id-relationship' => 'self',
                ],
                [
                    'external-id-type' => 'grant_number',
                    'external-id-value' => 'RGPIN-2021',
                    'external-id-relationship' => 'part-of',
                ],
            ]],
            'contributors' => ['contributor' => [
                [
                    'contributor-orcid' => ['path' => '0000-0002-1825-0097'],
                    'credit-name' => ['value' => 'Josiah Carberry'],
                    'contributor-attributes' => ['contributor-role' => 'lead'],
                ],
                [
                    'credit-name' => ['value' => 'Someone Else'],
                    'contributor-attributes' => ['contributor-role' => 'http://credit.niso.org/contributor-roles/funding-acquisition/'],
                ],
                ['credit-name' => ['value' => 'No Role']],
            ]],
            'organization' => [
                'name' => 'Natural Sciences and Engineering Research Council',
                'address' => ['city' => 'Ottawa', 'country' => 'CA'],
                'disambiguated-organization' => ['disambiguated-organization-identifier' => 'https://ror.org/01h531d29', 'disambiguation-source' => 'ROR'],
            ],
        ]);
    });

    it('takes an external identifier of any relationship, unlike a work (c2/f03, f04)', function (ExternalIdRelationship $relationship): void {
        $input = funding(externalIds: [new ExternalId('grant_number', 'RGPIN-2021', $relationship)]);

        expect($input->externalIds[0]->relationship)->toBe($relationship);
    })->with([ExternalIdRelationship::PartOf, ExternalIdRelationship::FundedBy]);

    it('takes a start date alone, a partial address or a negative amount (c2/f14, f10, f11, f33)', function (): void {
        $input = funding(
            organization: new Organization('NSERC', new OrganizationAddress(null, 'CA'), DisambiguatedOrganization::ror('https://ror.org/01h531d29')),
            startDate: FuzzyDate::fromString('2022-03'),
            amount: new Amount('-5', 'CAD'),
        );

        expect($input->toArray())->toHaveKey('start-date')
            ->not->toHaveKey('end-date')
            ->and($input->toArray()['organization']['address'])->toBe(['country' => 'CA'])
            ->and($input->amount?->value)->toBe('-5');
    });

    it('refuses what ORCID would reject, before sending', function (Closure $build, string $message): void {
        expect($build)->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'no external identifier (9023, c2/f02)' => [fn (): FundingInput => funding(externalIds: []), 'external identifier'],
        'no disambiguated organization (9045, c2/f09)' => [fn (): FundingInput => funding(organization: new Organization('NSERC', new OrganizationAddress('Ottawa', 'CA'))), 'disambiguated organization'],
        'no organization address (9047, c2/g10)' => [fn (): FundingInput => funding(organization: new Organization('NSERC', disambiguatedOrganization: DisambiguatedOrganization::ror('https://ror.org/01h531d29'))), 'address'],
        'an address with neither city nor country' => [fn (): FundingInput => funding(organization: new Organization('NSERC', new OrganizationAddress(' ', null), DisambiguatedOrganization::ror('https://ror.org/01h531d29'))), 'address'],
        'blank organization name (9047, c2/g11)' => [fn (): FundingInput => funding(organization: new Organization(' ', new OrganizationAddress('Ottawa', 'CA'), DisambiguatedOrganization::ror('https://ror.org/01h531d29'))), 'organization name'],
        'end date without a start date (9050, c2/f12)' => [fn (): FundingInput => funding(endDate: FuzzyDate::fromString('2022')), 'start date'],
        'end date before the start date' => [fn (): FundingInput => funding(startDate: FuzzyDate::fromString('2022'), endDate: FuzzyDate::fromString('2021')), 'before the start date'],
        'a subtitle (9047, c2/f19)' => [fn (): FundingInput => funding(title: new Title('Ocean', 'A review')), 'subtitle'],
        'title over 1000 characters (9047, c2/f26)' => [fn (): FundingInput => funding(title: new Title(str_repeat('x', 1001))), 'title is longer than 1000'],
        'translated title over 1000 characters' => [fn (): FundingInput => funding(title: new Title('Ocean', translatedTitle: new TranslatedTitle(str_repeat('é', 1001), 'fr'))), 'translated title is longer than 1000'],
        'translated title with a malformed language (9047, c2/f30)' => [fn (): FundingInput => funding(title: new Title('Ocean', translatedTitle: new TranslatedTitle('Océan', 'french'))), 'language code'],
        'organization-defined type over 255 characters (9047, c2/f29)' => [fn (): FundingInput => funding(organizationDefinedType: str_repeat('x', 256)), 'organization-defined type is longer than 255'],
        'short description over 5000 characters (9047, c2/g17)' => [fn (): FundingInput => funding(shortDescription: str_repeat('x', 5001)), 'short description is longer than 5000'],
        'contributor role unknown to ORCID (9058, c2/f16)' => [fn (): FundingInput => funding(contributors: [new FundingContributor('A', rawRole: 'author')]), 'contributor role'],
        'contributor credit name over 150 characters' => [fn (): FundingInput => funding(contributors: [new FundingContributor(str_repeat('x', 151))]), 'credit name is longer than 150'],
        'contributor with an invalid iD' => [fn (): FundingContributor => new FundingContributor('A', '0000-0002-1825-0098'), 'bad checksum'],
    ]);

    it('refuses an amount ORCID would reject', function (string $value, string $currency, string $message): void {
        expect(fn (): Amount => new Amount($value, $currency))->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'thousands separators (9054, c2/f23)' => ['1,000,000', 'CAD', 'Invalid amount'],
        'a space (9054, c2/g03)' => ['1 000', 'CAD', 'Invalid amount'],
        'a decimal comma (9054, c2/g04)' => ['1000,50', 'CAD', 'Invalid amount'],
        'not a number (9054, c2/f22)' => ['a lot', 'CAD', 'Invalid amount'],
        'no value (9020, c2/f24)' => [' ', 'CAD', 'Invalid amount'],
        'no currency (9047, c2/f20)' => ['1000', '', 'Invalid currency'],
        'a malformed currency (9047, c2/f21)' => ['1000', 'dollars', 'Invalid currency'],
    ]);

    it('takes the amounts ORCID takes (c2/g01, g02, g05, g07, g08), uppercasing the currency', function (string $value): void {
        $amount = new Amount(" {$value} ", 'cad');

        expect($amount->toArray())->toBe(['value' => $value, 'currency-code' => 'CAD']);
    })->with(['1000', '1e5', '0', '.5', '12345678901234567890.123']);

    it('leaves out blank optional strings (ORCID drops or keeps them blank, c2/g13)', function (): void {
        $input = funding(title: new Title(' Ocean acidification '), organizationDefinedType: ' ', shortDescription: '');

        expect($input->toArray())->not->toHaveKeys(['organization-defined-type', 'short-description'])
            ->and($input->toArray()['title'])->toBe(['title' => ['value' => 'Ocean acidification']]);
    });
});

describe('Funding', function (): void {
    it('keeps values unknown to the SDK', function (): void {
        $funding = Funding::fromArray([
            'put-code' => 42,
            'type' => 'fellowship',
            'title' => ['title' => ['value' => 'Ocean acidification'], 'translated-title' => null],
            'amount' => ['value' => 'lots', 'currency-code' => 'CAD'],
            'contributors' => ['contributor' => [[
                'contributor-orcid' => ['path' => 'not-an-orcid'],
                'credit-name' => ['value' => 'Josiah Carberry'],
                'contributor-email' => null,
                'contributor-attributes' => ['contributor-role' => 'sponsor'],
            ]]],
            'short-description' => ' ',
            'visibility' => 'public',
        ]);

        expect($funding->putCode)->toBe(42)
            ->and($funding->type)->toBeNull()
            ->and($funding->rawType)->toBe('fellowship')
            ->and($funding->title?->title)->toBe('Ocean acidification')
            ->and($funding->amount)->toBeNull()
            ->and($funding->contributors[0]->orcid)->toBeNull()
            ->and($funding->contributors[0]->creditName)->toBe('Josiah Carberry')
            ->and($funding->contributors[0]->role)->toBeNull()
            ->and($funding->contributors[0]->rawRole)->toBe('sponsor')
            ->and($funding->shortDescription)->toBeNull()
            ->and($funding->organization)->toBeNull()
            ->and($funding->externalIds)->toBe([])
            ->and($funding->startDate)->toBeNull();
    });

    it('reads the enums and the amount it knows', function (): void {
        $funding = Funding::fromArray([
            'put-code' => 42,
            'type' => 'salary-award',
            'amount' => ['value' => '125000.5', 'currency-code' => 'CAD'],
            'contributors' => ['contributor' => [['contributor-attributes' => ['contributor-role' => 'co-lead']]]],
        ]);

        expect($funding->type)->toBe(FundingType::SalaryAward)
            ->and($funding->rawType)->toBe('salary-award')
            ->and((string) $funding->amount)->toBe('125000.5 CAD')
            ->and($funding->contributors[0]->role)->toBe(FundingContributorRole::CoLead)
            ->and($funding->title)->toBeNull();
    });
});

describe('FundingGroups', function (): void {
    // hand-built: the sandbox only holds fundings from our client, each in its own group
    it('prefers the highest display index, the first one listed on a tie', function (): void {
        $list = FundingGroups::fromArray(['group' => [
            ['external-ids' => ['external-id' => [['external-id-type' => 'grant_number', 'external-id-value' => 'A-1']]], 'funding-summary' => [
                ['put-code' => 1, 'display-index' => '0', 'type' => 'fellowship'],
                ['put-code' => 2, 'display-index' => '3'],
                ['put-code' => 3, 'display-index' => '1'],
            ]],
            ['external-ids' => ['external-id' => []], 'funding-summary' => [['put-code' => 4, 'display-index' => '0'], ['put-code' => 5, 'display-index' => '0']]],
            ['funding-summary' => []],
        ]]);

        expect($list->groups)->toHaveCount(2)
            ->and($list->groups[0]->externalIds[0]->value)->toBe('A-1')
            ->and($list->groups[1]->externalIds)->toBe([])
            ->and(array_map(fn (FundingSummary $summary): int => $summary->putCode, $list->preferred()))->toBe([2, 4])
            ->and(array_map(fn (FundingSummary $summary): int => $summary->putCode, $list->summaries()))->toBe([1, 2, 3, 4, 5])
            ->and($list->groups[0]->summaries[0]->type)->toBeNull()
            ->and($list->groups[0]->summaries[0]->rawType)->toBe('fellowship');
    });

    it('reads a record without fundings', function (): void {
        $list = FundingGroups::fromArray(['last-modified-date' => null, 'group' => [], 'path' => '/0000-0002-1825-0097/fundings']);

        expect($list->groups)->toBe([])
            ->and($list->preferred())->toBe([])
            ->and($list->summaries())->toBe([]);
    });
});
