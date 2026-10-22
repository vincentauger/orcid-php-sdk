<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Activities\ActivitiesSummary;
use VincentAuger\OrcidSdk\Data\Person\Person;
use VincentAuger\OrcidSdk\Enums\AffiliationType;

// The sandbox record has few person fields, so this payload is built from ORCID's v3.0 schema

it('reads every person section', function (): void {
    $person = Person::fromArray([
        'last-modified-date' => ['value' => 1792694211225],
        'name' => [
            'given-names' => ['value' => 'Josiah'],
            'family-name' => ['value' => 'Carberry'],
            'credit-name' => ['value' => 'J. S. Carberry'],
            'visibility' => 'public',
        ],
        'other-names' => ['other-name' => [['content' => 'Jo Carberry'], ['content' => '  ']]],
        'biography' => ['content' => 'Psychoceramics.', 'visibility' => 'public'],
        'researcher-urls' => ['researcher-url' => [
            ['url-name' => 'Lab', 'url' => ['value' => 'https://example.org/lab']],
            ['url-name' => 'Broken', 'url' => null],
        ]],
        'emails' => ['email' => [
            ['email' => 'j@example.org', 'primary' => false, 'verified' => true, 'visibility' => 'limited'],
            ['email' => 'josiah@example.org', 'primary' => true, 'verified' => true, 'visibility' => 'public'],
        ]],
        'addresses' => ['address' => [['country' => ['value' => 'US']]]],
        'keywords' => ['keyword' => [['content' => 'ceramics'], ['content' => 'psychology']]],
        'external-identifiers' => ['external-identifier' => [[
            'external-id-type' => 'Scopus Author ID',
            'external-id-value' => '1234567',
            'external-id-url' => ['value' => 'https://www.scopus.com/authid/detail.uri?authorId=1234567'],
            'external-id-relationship' => 'self',
        ]]],
    ]);

    expect($person->name?->displayName())->toBe('J. S. Carberry')
        ->and($person->otherNames)->toBe(['Jo Carberry'])
        ->and($person->biography)->toBe('Psychoceramics.')
        ->and($person->researcherUrls)->toHaveCount(1)
        ->and($person->researcherUrls[0]->name)->toBe('Lab')
        ->and($person->emails)->toHaveCount(2)
        ->and($person->primaryEmail()?->email)->toBe('josiah@example.org')
        ->and($person->countries)->toBe(['US'])
        ->and($person->keywords)->toBe(['ceramics', 'psychology'])
        ->and($person->externalIdentifiers[0]->type)->toBe('Scopus Author ID')
        ->and($person->externalIdentifiers[0]->url)->toBe('https://www.scopus.com/authid/detail.uri?authorId=1234567')
        ->and($person->lastModifiedAt?->format('Y'))->toBe('2026');
});

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
