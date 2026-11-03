<?php

declare(strict_types=1);

use Saloon\Data\RecordedResponse;
use Saloon\Helpers\Storage;
use Tests\Fixtures\OrcidFixture;

/**
 * Store a response through OrcidFixture into a temporary directory and return what was written
 *
 * @param  array<string, mixed>  $headers
 * @param  array<string, mixed>  $body
 * @return array{statusCode: int, headers: array<string, mixed>, data: string}
 */
function storeThroughFixture(array $headers, array $body): array
{
    $dir = sys_get_temp_dir().'/orcid-fixture-test-'.bin2hex(random_bytes(4));
    mkdir($dir);

    $fixture = new OrcidFixture('redaction', new Storage($dir, true));
    $fixture->store(new RecordedResponse(200, $headers, json_encode($body, JSON_THROW_ON_ERROR)));

    /** @var array{statusCode: int, headers: array<string, mixed>, data: string} $stored */
    $stored = json_decode((string) file_get_contents($dir.'/redaction.json'), true);

    unlink($dir.'/redaction.json');
    rmdir($dir);

    return $stored;
}

it('replaces ORCID iDs in the body and the Location header', function (): void {
    $stored = storeThroughFixture(
        ['Location' => 'https://api.sandbox.orcid.org/v3.0/0000-0001-2345-678X/employment/12345'],
        ['path' => '/0000-0001-2345-678X/employments'],
    );

    expect($stored['headers']['Location'])->toBe('https://api.sandbox.orcid.org/v3.0/'.OrcidFixture::PLACEHOLDER_ID.'/employment/12345')
        ->and($stored['data'])->toContain(OrcidFixture::PLACEHOLDER_ID)
        ->not->toContain('0000-0001-2345-678X');
});

it('replaces client ids', function (): void {
    $stored = storeThroughFixture([], [
        'source' => ['source-client-id' => [
            'uri' => 'https://sandbox.orcid.org/client/APP-ABCDEFGHIJ123456',
            'path' => 'APP-ABCDEFGHIJ123456',
        ]],
    ]);

    expect($stored['data'])->toContain(OrcidFixture::PLACEHOLDER_CLIENT_ID)
        ->not->toContain('APP-ABCDEFGHIJ123456');
});

it('only keeps list items created by tests', function (): void {
    $stored = storeThroughFixture([], ['affiliation-group' => [
        ['summaries' => [['employment-summary' => ['put-code' => 1, 'department-name' => 'Real department']]]],
        ['summaries' => [['employment-summary' => ['put-code' => 2, 'department-name' => OrcidFixture::TEST_MARKER.' test']]]],
    ]]);

    expect($stored['data'])->not->toContain('Real department')
        ->and(json_decode($stored['data'], true)['affiliation-group'])->toHaveCount(1);
});

it('only keeps test items in nested lists (activities summary)', function (): void {
    $stored = storeThroughFixture([], ['employments' => ['affiliation-group' => [
        ['summaries' => [['employment-summary' => ['department-name' => 'Real department']]]],
    ]]]);

    expect($stored['data'])->not->toContain('Real department');
});

it('only keeps test items of other sections, by their own marker field', function (): void {
    $stored = storeThroughFixture([], ['works' => ['group' => [
        ['work-summary' => [['put-code' => 1, 'title' => ['title' => ['value' => 'Real work']]]]],
        ['work-summary' => [['put-code' => 2, 'title' => ['title' => ['value' => OrcidFixture::TEST_MARKER.' work']]]]],
    ]], 'fundings' => ['group' => [
        ['funding-summary' => [['put-code' => 3, 'title' => ['title' => ['value' => 'Real funding']]]]],
    ]]]);

    $data = json_decode($stored['data'], true);

    expect($stored['data'])->not->toContain('Real work')
        ->not->toContain('Real funding')
        ->and($data['works']['group'])->toHaveCount(1)
        ->and($data['fundings']['group'])->toBeEmpty();
});

it('keeps the other sources\' summaries in a group holding a test item', function (): void {
    $stored = storeThroughFixture([], ['group' => [
        ['work-summary' => [
            ['put-code' => 1, 'title' => ['title' => ['value' => OrcidFixture::TEST_MARKER.' work']]],
            ['put-code' => 2, 'title' => ['title' => ['value' => 'Hand-added work, same DOI']]],
        ]],
        ['work-summary' => [['put-code' => 3, 'title' => ['title' => ['value' => 'Real work']]]]],
    ]]);

    $groups = json_decode($stored['data'], true)['group'];

    expect($groups)->toHaveCount(1)
        ->and(array_column($groups[0]['work-summary'], 'put-code'))->toBe([1, 2])
        ->and($stored['data'])->not->toContain('Real work');
});

it('keeps lists without filtered summaries', function (): void {
    $body = ['external-ids' => ['external-id' => [['external-id-type' => 'doi', 'external-id-value' => '10.1/x']]]];

    expect(json_decode(storeThroughFixture([], $body)['data'], true))->toBe($body);
});

it('replaces OAuth tokens', function (): void {
    $stored = storeThroughFixture([], [
        'access_token' => 'secret-access',
        'refresh_token' => 'secret-refresh',
        'name' => 'Jane Doe',
        'orcid' => '0000-0001-2345-678X',
        'scope' => '/activities/update',
    ]);

    expect(json_decode($stored['data'], true))->toBe([
        'access_token' => OrcidFixture::REDACTED,
        'refresh_token' => OrcidFixture::REDACTED,
        'name' => 'Jane Doe',
        'orcid' => OrcidFixture::PLACEHOLDER_ID,
        'scope' => '/activities/update',
    ]);
});

it('keeps profile content', function (): void {
    $body = [
        'name' => ['given-names' => ['value' => 'Jane'], 'family-name' => ['value' => 'Doe']],
        'keywords' => ['keyword' => [['content' => 'ceramics']]],
        'organization' => ['name' => 'Fisheries and Oceans Canada'],
    ];

    expect(json_decode(storeThroughFixture([], $body)['data'], true))->toBe($body);
});
