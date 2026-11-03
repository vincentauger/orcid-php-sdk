<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Person\PersonExternalIdentifierInput;

it('sends the type, value and URL as a self identifier, and the put-code on an update', function (): void {
    $input = new PersonExternalIdentifierInput('Scopus Author ID', '1234567', 'https://www.scopus.com/authid/detail.uri?authorId=1234567');

    expect($input->toArray())->toBe([
        'external-id-type' => 'Scopus Author ID',
        'external-id-value' => '1234567',
        'external-id-url' => ['value' => 'https://www.scopus.com/authid/detail.uri?authorId=1234567'],
        'external-id-relationship' => 'self',
    ])->and($input->toArray(8675))->toHaveKey('put-code', 8675);
});

it('trims every field', function (): void {
    $input = new PersonExternalIdentifierInput(' ResearcherID ', ' A-1234-2008 ', ' https://www.researcherid.com/rid/A-1234-2008 ');

    expect([$input->type, $input->value, $input->url])
        ->toBe(['ResearcherID', 'A-1234-2008', 'https://www.researcherid.com/rid/A-1234-2008']);
});

it('refuses a blank type, value (9047) or URL (9020)', function (string $type, string $value, string $url, string $field): void {
    expect(fn (): PersonExternalIdentifierInput => new PersonExternalIdentifierInput($type, $value, $url))
        ->toThrow(InvalidArgumentException::class, "non-blank {$field}");
})->with([
    'type' => [' ', '1234567', 'https://example.org', 'type'],
    'value' => ['Scopus Author ID', '', 'https://example.org', 'value'],
    'URL' => ['Scopus Author ID', '1234567', ' ', 'URL'],
]);

it('accepts a type and a value of 255 characters and refuses more (9020)', function (): void {
    $long = str_repeat('é', 255);

    expect(new PersonExternalIdentifierInput($long, $long, 'https://example.org')->value)->toHaveLength(255)
        ->and(fn (): PersonExternalIdentifierInput => new PersonExternalIdentifierInput($long.'é', '1', 'https://example.org'))
        ->toThrow(InvalidArgumentException::class, 'type is longer than 255 characters')
        ->and(fn (): PersonExternalIdentifierInput => new PersonExternalIdentifierInput('Scopus Author ID', $long.'é', 'https://example.org'))
        ->toThrow(InvalidArgumentException::class, 'value is longer than 255 characters');
});
