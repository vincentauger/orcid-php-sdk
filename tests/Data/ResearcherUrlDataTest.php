<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Person\ResearcherUrlInput;

it('sends the URL and its name, and the put-code on an update', function (): void {
    expect(new ResearcherUrlInput('https://example.org/lab', 'Lab')->toArray())
        ->toBe(['url-name' => 'Lab', 'url' => ['value' => 'https://example.org/lab']])
        ->and(new ResearcherUrlInput('https://example.org/lab')->toArray(48702))
        ->toBe(['put-code' => 48702, 'url' => ['value' => 'https://example.org/lab']]);
});

it('trims both, and leaves out a blank name (9047)', function (): void {
    $input = new ResearcherUrlInput(' https://example.org/lab ', '  ');

    expect($input->url)->toBe('https://example.org/lab')
        ->and($input->name)->toBeNull()
        ->and($input->toArray())->toBe(['url' => ['value' => 'https://example.org/lab']]);
});

it('refuses a blank URL (9020)', function (string $url): void {
    new ResearcherUrlInput($url);
})->with(['', '   '])->throws(InvalidArgumentException::class, 'non-blank URL');

it('accepts a URL of 2000 characters and refuses more (9020)', function (): void {
    expect(new ResearcherUrlInput(str_repeat('x', 2000))->url)->toHaveLength(2000)
        ->and(fn (): ResearcherUrlInput => new ResearcherUrlInput(str_repeat('x', 2001)))
        ->toThrow(InvalidArgumentException::class, 'URL is longer than 2000 characters');
});

it('accepts a name of 350 characters and refuses more (9047)', function (): void {
    expect(new ResearcherUrlInput('https://example.org', str_repeat('é', 350))->name)->toHaveLength(350)
        ->and(fn (): ResearcherUrlInput => new ResearcherUrlInput('https://example.org', str_repeat('é', 351)))
        ->toThrow(InvalidArgumentException::class, 'name is longer than 350 characters');
});

it('sends any URL, as ORCID does not check it', function (): void {
    expect(new ResearcherUrlInput('example.org/lab')->url)->toBe('example.org/lab');
});
