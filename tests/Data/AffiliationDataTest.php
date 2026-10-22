<?php

declare(strict_types=1);

use Saloon\Data\RecordedResponse;
use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Data\Affiliations\AffiliationInput;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Support\OrcidId;

function dfo(?string $city = 'Ottawa', ?string $country = 'CA', ?DisambiguatedOrganization $id = null): Organization
{
    return new Organization(
        'Fisheries and Oceans Canada',
        new OrganizationAddress($city, $country, 'ON'),
        $id ?? DisambiguatedOrganization::ror('https://ror.org/02qa1x782'),
    );
}

/**
 * The body of a fixture recorded during A1
 *
 * @return array<string, mixed>
 */
function a1Body(string $name): array
{
    $file = __DIR__.'/../Fixtures/Saloon/a1/'.$name.'.json';
    $recorded = RecordedResponse::fromFile((string) file_get_contents($file));

    /** @var array<string, mixed> */
    return json_decode((string) $recorded->data, true);
}

describe('FuzzyDate', function (): void {
    it('parses and prints partial dates', function (string $input, ?int $month, ?int $day): void {
        $date = FuzzyDate::fromString($input);

        expect($date->month)->toBe($month)
            ->and($date->day)->toBe($day)
            ->and((string) $date)->toBe($input);
    })->with([
        ['2021', null, null],
        ['2021-01', 1, null],
        ['2021-01-05', 1, 5],
    ]);

    it('sends the documented {value} format', function (): void {
        expect(FuzzyDate::fromString('2021-01-05')->toArray())->toBe([
            'year' => ['value' => '2021'],
            'month' => ['value' => '01'],
            'day' => ['value' => '05'],
        ]);
    });

    it('reads wrapped and plain values', function (): void {
        expect(FuzzyDate::fromArray(['year' => ['value' => '2021'], 'month' => null, 'day' => null]))->toEqual(new FuzzyDate(2021))
            ->and(FuzzyDate::fromArray(['year' => '2021', 'month' => '01']))->toEqual(new FuzzyDate(2021, 1))
            ->and(FuzzyDate::fromArray(null))->toBeNull();
    });

    it('rejects impossible dates', function (string $input): void {
        FuzzyDate::fromString($input);
    })->with(['2021-13', '2021-02-30', '21-01-01', ''])->throws(InvalidArgumentException::class);

    it('compares dates only to their common precision', function (): void {
        expect(FuzzyDate::fromString('2020')->isBefore(FuzzyDate::fromString('2021-05')))->toBeTrue()
            ->and(FuzzyDate::fromString('2021')->isBefore(FuzzyDate::fromString('2021-05')))->toBeFalse()
            ->and(FuzzyDate::fromString('2021-04')->isBefore(FuzzyDate::fromString('2021-05')))->toBeTrue()
            ->and(FuzzyDate::fromString('2021-05-02')->isBefore(FuzzyDate::fromString('2021-05-03')))->toBeTrue()
            ->and(FuzzyDate::fromString('2021-05')->isBefore(FuzzyDate::fromString('2021-05-03')))->toBeFalse();
    });
});

describe('OrcidId', function (): void {
    it('normalises bare iDs and URLs', function (string $input): void {
        expect(OrcidId::normalize($input))->toBe('0000-0002-1825-0097');
    })->with([
        '0000-0002-1825-0097',
        'https://orcid.org/0000-0002-1825-0097',
        'https://sandbox.orcid.org/0000-0002-1825-0097/',
        ' 0000-0002-1825-0097 ',
    ]);

    it('accepts an X check digit', function (): void {
        expect(OrcidId::normalize('0000-0002-9079-593x'))->toBe('0000-0002-9079-593X');
    });

    it('rejects bad iDs', function (string $input): void {
        expect(OrcidId::isValid($input))->toBeFalse();
    })->with(['0000-0002-1825-0098', '0000-0002-1825', 'not an id']);
});

describe('AffiliationInput', function (): void {
    it('builds the ORCID body, leaving out empty fields', function (): void {
        $input = new AffiliationInput(
            organization: dfo(),
            departmentName: 'Science',
            roleTitle: '  ',
            startDate: FuzzyDate::fromString('2021-01-05'),
            externalIds: [new ExternalId('grant_number', 'ABC-1', ExternalIdRelationship::Self, 'https://example.org/abc')],
        );

        expect($input->roleTitle)->toBeNull()
            ->and($input->toArray())->toBe([
                'department-name' => 'Science',
                'start-date' => ['year' => ['value' => '2021'], 'month' => ['value' => '01'], 'day' => ['value' => '05']],
                'organization' => [
                    'name' => 'Fisheries and Oceans Canada',
                    'address' => ['city' => 'Ottawa', 'region' => 'ON', 'country' => 'CA'],
                    'disambiguated-organization' => [
                        'disambiguated-organization-identifier' => 'https://ror.org/02qa1x782',
                        'disambiguation-source' => 'ROR',
                    ],
                ],
                'external-ids' => ['external-id' => [[
                    'external-id-type' => 'grant_number',
                    'external-id-value' => 'ABC-1',
                    'external-id-url' => ['value' => 'https://example.org/abc'],
                    'external-id-relationship' => 'self',
                ]]],
            ]);
    });

    it('adds the put-code for updates', function (): void {
        expect(new AffiliationInput(dfo())->toArray(123))->toHaveKey('put-code', 123);
    });

    it('rejects what ORCID would reject', function (Closure $build, string $message): void {
        expect($build)->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'blank city' => [fn (): AffiliationInput => new AffiliationInput(dfo(city: ' ')), 'organization city'],
        'no city' => [fn (): AffiliationInput => new AffiliationInput(dfo(city: null)), 'organization city'],
        'no country' => [fn (): AffiliationInput => new AffiliationInput(dfo(country: '')), 'organization country'],
        'no disambiguated organization' => [fn (): AffiliationInput => new AffiliationInput(new Organization('DFO', new OrganizationAddress('Ottawa', 'CA'))), 'disambiguated organization'],
        'end before start' => [fn (): AffiliationInput => new AffiliationInput(dfo(), startDate: FuzzyDate::fromString('2022'), endDate: FuzzyDate::fromString('2021')), 'is before the start date'],
    ]);
});

describe('Affiliation', function (): void {
    it('reads an item recorded from the sandbox', function (): void {
        $affiliation = Affiliation::fromArray(AffiliationType::Employment, a1Body('e3a-get'));

        expect($affiliation->type)->toBe(AffiliationType::Employment)
            ->and($affiliation->putCode)->toBeInt()
            ->and($affiliation->departmentName)->toBe('orcid-php-sdk A1 E1')
            ->and($affiliation->roleTitle)->toBe('Tester')
            ->and((string) $affiliation->startDate)->toBe('2021-01-05')
            ->and((string) $affiliation->endDate)->toBe('2023')
            ->and($affiliation->organization?->name)->toBe('Fisheries and Oceans Canada')
            ->and($affiliation->organization?->address?->city)->toBe('Ottawa')
            ->and($affiliation->organization?->disambiguatedOrganization?->identifier)->toBe('https://ror.org/02qa1x782')
            ->and($affiliation->source?->clientId)->toBe('APP-0000000000000000')
            ->and($affiliation->source?->isRecordOwner())->toBeFalse()
            ->and($affiliation->createdAt)->toBeInstanceOf(DateTimeImmutable::class)
            ->and($affiliation->visibility)->toBe('public');
    });

    it('reads cleared fields as null', function (): void {
        $affiliation = Affiliation::fromArray(AffiliationType::Employment, a1Body('e3b-get'));

        expect($affiliation->roleTitle)->toBeNull()
            ->and($affiliation->endDate)->toBeNull();
    });

    it('recognises items added by the record owner', function (): void {
        $affiliation = Affiliation::fromArray(AffiliationType::Employment, [
            'put-code' => 1,
            'source' => ['source-orcid' => ['path' => '0000-0002-1825-0097'], 'source-client-id' => null],
        ]);

        expect($affiliation->source?->isRecordOwner())->toBeTrue()
            ->and($affiliation->organization)->toBeNull();
    });
});
