<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Activities\ActivitiesSummary;
use VincentAuger\OrcidSdk\Data\Person\Keyword;
use VincentAuger\OrcidSdk\Data\Person\Person;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Enums\Visibility;
use VincentAuger\OrcidSdk\Requests\Person\GetPerson;

it('reads every person section of a recorded record as typed items', function (): void {
    // the Member API's person section, recorded with a test item in every writable section (RecordResourceTest):
    // re-recording it there means updating the put-codes below
    $mockClient = new MockClient([GetPerson::class => new OrcidFixture('record/person-member/person')]);
    $person = $this->getMemberApiClient()->withMockClient($mockClient)->person();

    expect($person->name?->displayName())->toBe('V JP Auger')
        ->and($person->lastModifiedAt?->format('Y'))->toBe('2026')
        ->and($person->biography?->content)->toBe('A test bio.')
        ->and($person->biography?->visibility)->toBe(Visibility::Public)
        ->and($person->primaryEmail()?->email)->toBe('vincentauger@mailinator.com');

    // added by the researcher on the website: their own source, no client
    expect($person->otherNames)->toHaveCount(2)
        ->and($person->otherNames[0]->content)->toBe('JP Auger')
        ->and($person->otherNames[0]->putCode)->toBe(19146)
        ->and($person->otherNames[0]->displayIndex)->toBe(2)
        ->and($person->otherNames[0]->source?->isRecordOwner())->toBeTrue()
        ->and($person->researcherUrls[0]->name)->toBe('DFO')
        ->and($person->researcherUrls[0]->url)->toBe('https://dfo-mpo.gc.ca')
        ->and($person->addresses[0]->country)->toBe('CA')
        ->and($person->addresses[0]->putCode)->toBe(8230);

    // added by the tests' client
    expect($person->otherNames[1]->content)->toBe('orcid-php-sdk person')
        ->and($person->otherNames[1]->putCode)->toBe(19164)
        ->and($person->otherNames[1]->visibility)->toBe(Visibility::Public)
        ->and($person->otherNames[1]->source?->isClient('APP-0000000000000000'))->toBeTrue()
        ->and($person->otherNames[1]->createdAt?->format('Y'))->toBe('2026')
        ->and($person->otherNames[1]->path)->toBe('/0000-0002-1825-0097/other-names/19164')
        ->and($person->researcherUrls[1]->name)->toBe('orcid-php-sdk person')
        ->and($person->researcherUrls[1]->url)->toBe('https://example.org/orcid-php-sdk-person')
        ->and($person->researcherUrls[1]->putCode)->toBe(48713)
        ->and($person->addresses[1]->country)->toBe('AQ')
        ->and(array_map(fn (Keyword $keyword): string => $keyword->content, $person->keywords))->toBe(['orcid-php-sdk person'])
        ->and($person->keywords[0]->putCode)->toBe(8515)
        ->and($person->externalIdentifiers)->toHaveCount(1)
        ->and($person->externalIdentifiers[0]->externalId->type)->toBe('Scopus Author ID')
        ->and($person->externalIdentifiers[0]->externalId->value)->toBe('orcid-php-sdk-person')
        ->and($person->externalIdentifiers[0]->externalId->url)->toBe('https://example.org/orcid-php-sdk-person')
        ->and($person->externalIdentifiers[0]->putCode)->toBe(8682)
        ->and($person->externalIdentifiers[0]->displayIndex)->toBe(1);
});

it('leaves out blank items', function (): void {
    $person = Person::fromArray([
        'other-names' => ['other-name' => [['put-code' => 1, 'content' => '  ']]],
        'researcher-urls' => ['researcher-url' => [['put-code' => 2, 'url-name' => 'Broken', 'url' => null]]],
    ]);

    expect($person->otherNames)->toBe([])
        ->and($person->researcherUrls)->toBe([]);
});

it('reads an empty person section', function (): void {
    $person = Person::fromArray(['biography' => ['content' => null], 'keywords' => ['keyword' => []]]);

    expect($person->name)->toBeNull()
        ->and($person->biography)->toBeNull()
        ->and($person->keywords)->toBe([])
        ->and($person->addresses)->toBe([]);
});

it('refuses a person item without a put-code', function (): void {
    Person::fromArray(['keywords' => ['keyword' => [['content' => 'ceramics']]]]);
})->throws(UnexpectedValueException::class, 'keyword has no put-code');

it('falls back to given and family names', function (): void {
    $person = Person::fromArray(['name' => ['given-names' => ['value' => 'Josiah'], 'family-name' => ['value' => 'Carberry']]]);

    expect($person->name?->displayName())->toBe('Josiah Carberry')
        ->and($person->primaryEmail())->toBeNull();
});

it('reads an empty activities summary', function (): void {
    $summary = ActivitiesSummary::fromArray([
        'works' => ['group' => [['work-summary' => []], ['work-summary' => []]]],
        'fundings' => ['group' => []],
    ]);

    expect($summary->workCount)->toBe(2)
        ->and($summary->fundingCount)->toBe(0)
        ->and($summary->affiliations(AffiliationType::Employment))->toBe([]);
});
