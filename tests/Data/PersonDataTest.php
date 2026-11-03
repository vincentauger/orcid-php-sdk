<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Activities\ActivitiesSummary;
use VincentAuger\OrcidSdk\Data\Person\Keyword;
use VincentAuger\OrcidSdk\Data\Person\Person;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Enums\Visibility;

// The sandbox record has few person fields, so this payload is built from ORCID's v3.0 schema

it('reads every person section as typed items', function (): void {
    $source = ['source-client-id' => ['path' => 'APP-0000000000000000'], 'source-name' => ['value' => 'OSP']];
    $item = static fn (int $putCode, array $fields): array => [
        ...$fields,
        'put-code' => $putCode,
        'visibility' => 'public',
        'display-index' => 1,
        'source' => $source,
        'created-date' => ['value' => 1792694211225],
        'last-modified-date' => ['value' => 1792694211225],
        'path' => '/0000-0002-1825-0097/item/'.$putCode,
    ];

    $person = Person::fromArray([
        'last-modified-date' => ['value' => 1792694211225],
        'name' => [
            'given-names' => ['value' => 'Josiah'],
            'family-name' => ['value' => 'Carberry'],
            'credit-name' => ['value' => 'J. S. Carberry'],
            'visibility' => 'public',
        ],
        'other-names' => ['other-name' => [$item(1, ['content' => 'Jo Carberry']), $item(2, ['content' => '  '])]],
        'biography' => ['content' => 'Psychoceramics.', 'visibility' => 'limited', 'created-date' => ['value' => 1792694211225]],
        'researcher-urls' => ['researcher-url' => [
            $item(3, ['url-name' => 'Lab', 'url' => ['value' => 'https://example.org/lab']]),
            $item(4, ['url-name' => 'Broken', 'url' => null]),
        ]],
        'emails' => ['email' => [
            ['email' => 'j@example.org', 'primary' => false, 'verified' => true, 'visibility' => 'limited'],
            ['email' => 'josiah@example.org', 'primary' => true, 'verified' => true, 'visibility' => 'public'],
        ]],
        'addresses' => ['address' => [$item(5, ['country' => ['value' => 'US']])]],
        'keywords' => ['keyword' => [$item(6, ['content' => 'ceramics']), $item(7, ['content' => 'psychology'])]],
        'external-identifiers' => ['external-identifier' => [$item(8, [
            'external-id-type' => 'Scopus Author ID',
            'external-id-value' => '1234567',
            'external-id-url' => ['value' => 'https://www.scopus.com/authid/detail.uri?authorId=1234567'],
            'external-id-relationship' => 'self',
        ])]],
    ]);

    expect($person->name?->displayName())->toBe('J. S. Carberry')
        ->and($person->otherNames)->toHaveCount(1)
        ->and($person->otherNames[0]->content)->toBe('Jo Carberry')
        ->and($person->otherNames[0]->putCode)->toBe(1)
        ->and($person->otherNames[0]->visibility)->toBe(Visibility::Public)
        ->and($person->otherNames[0]->source?->isClient('APP-0000000000000000'))->toBeTrue()
        ->and($person->otherNames[0]->displayIndex)->toBe(1)
        ->and($person->otherNames[0]->createdAt?->format('Y'))->toBe('2026')
        ->and($person->otherNames[0]->path)->toBe('/0000-0002-1825-0097/item/1')
        ->and($person->biography?->content)->toBe('Psychoceramics.')
        ->and($person->biography?->visibility)->toBe(Visibility::Limited)
        ->and($person->biography?->createdAt?->format('Y'))->toBe('2026')
        ->and($person->researcherUrls)->toHaveCount(1)
        ->and($person->researcherUrls[0]->name)->toBe('Lab')
        ->and($person->researcherUrls[0]->url)->toBe('https://example.org/lab')
        ->and($person->researcherUrls[0]->putCode)->toBe(3)
        ->and($person->emails)->toHaveCount(2)
        ->and($person->primaryEmail()?->email)->toBe('josiah@example.org')
        ->and($person->addresses[0]->country)->toBe('US')
        ->and($person->addresses[0]->putCode)->toBe(5)
        ->and(array_map(fn (Keyword $keyword): string => $keyword->content, $person->keywords))->toBe(['ceramics', 'psychology'])
        ->and($person->keywords[1]->putCode)->toBe(7)
        ->and($person->keywords[1]->visibility)->toBe(Visibility::Public)
        ->and($person->externalIdentifiers[0]->externalId->type)->toBe('Scopus Author ID')
        ->and($person->externalIdentifiers[0]->externalId->url)->toBe('https://www.scopus.com/authid/detail.uri?authorId=1234567')
        ->and($person->externalIdentifiers[0]->putCode)->toBe(8)
        ->and($person->lastModifiedAt?->format('Y'))->toBe('2026');
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
