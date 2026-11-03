<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;

it('links a bare DOI to its resolver, as a self identifier', function (): void {
    $id = ExternalId::doi('10.5555/12345678');

    expect([$id->type, $id->value, $id->relationship, $id->url])
        ->toBe(['doi', '10.5555/12345678', ExternalIdRelationship::Self, 'https://doi.org/10.5555/12345678']);
});

it('links a bare value to the type\'s resolver, with the type\'s default relationship', function (string $constructor, string $value, string $type, ExternalIdRelationship $relationship, string $url): void {
    $id = ExternalId::{$constructor}($value);

    expect([$id->type, $id->value, $id->relationship, $id->url])->toBe([$type, $value, $relationship, $url]);
})->with([
    'isbn' => ['isbn', '9780306406157', 'isbn', ExternalIdRelationship::Self, 'https://www.worldcat.org/isbn/9780306406157'],
    'issn, part-of: a journal\'s ISSN' => ['issn', '1234-5679', 'issn', ExternalIdRelationship::PartOf, 'https://portal.issn.org/resource/ISSN/1234-5679'],
    'pmid' => ['pmid', '12345678', 'pmid', ExternalIdRelationship::Self, 'https://pubmed.ncbi.nlm.nih.gov/12345678'],
    'pmc' => ['pmc', 'PMC1234567', 'pmc', ExternalIdRelationship::Self, 'https://europepmc.org/article/pmc/PMC1234567'],
    'arxiv' => ['arxiv', '2101.00001', 'arxiv', ExternalIdRelationship::Self, 'https://arxiv.org/abs/2101.00001'],
    'handle' => ['handle', '1234/5678', 'handle', ExternalIdRelationship::Self, 'http://hdl.handle.net/1234/5678'],
    'rrid' => ['rrid', 'RRID:AB_123456', 'rrid', ExternalIdRelationship::Self, 'https://identifiers.org/rrid/RRID:AB_123456'],
]);

it('sends no URL for the types without a resolver', function (string $constructor, string $type): void {
    $id = ExternalId::{$constructor}('RGPIN-2021-1234');

    expect([$id->type, $id->value, $id->relationship, $id->url])
        ->toBe([$type, 'RGPIN-2021-1234', ExternalIdRelationship::Self, null]);
})->with([
    'grant number' => ['grantNumber', 'grant_number'],
    'proposal id' => ['proposalId', 'proposal-id'],
    'source work id' => ['sourceWorkId', 'source-work-id'],
]);

it('sends a URI as its own URL when it is a web address, and no URL otherwise (unchecked by ORCID, c1/q07)', function (string $value, ?string $url): void {
    $id = ExternalId::uri($value);

    expect([$id->type, $id->value, $id->relationship, $id->url])->toBe(['uri', $value, ExternalIdRelationship::Self, $url]);
})->with([
    'https' => ['https://example.org/report/7', 'https://example.org/report/7'],
    'http, any case' => ['HTTP://example.org/report/7', 'HTTP://example.org/report/7'],
    'urn' => ['urn:isbn:9780306406157', null],
    'ftp' => ['ftp://example.org/report.pdf', null],
]);

it('takes another relationship', function (): void {
    expect(ExternalId::doi('10.5555/abc', ExternalIdRelationship::VersionOf)->relationship)->toBe(ExternalIdRelationship::VersionOf)
        ->and(ExternalId::issn('1234-5679', ExternalIdRelationship::Self)->relationship)->toBe(ExternalIdRelationship::Self)
        ->and(ExternalId::grantNumber('RGPIN-2021', ExternalIdRelationship::PartOf)->relationship)->toBe(ExternalIdRelationship::PartOf);
});

it('keeps a value written as the resolver URL as both the value and the URL', function (string $constructor, string $value): void {
    $id = ExternalId::{$constructor}($value);

    expect([$id->value, $id->url])->toBe([$value, $value]);
})->with([
    'lowercase host' => ['doi', 'https://doi.org/10.5555/12345678'],
    'uppercase host' => ['doi', 'HTTPS://DOI.ORG/10.5555/ABC'],
    'a resolver with uppercase in its path' => ['issn', 'https://portal.issn.org/resource/ISSN/1234-5679'],
]);

it('refuses a value written as another URL, naming both accepted forms', function (string $value): void {
    expect(fn (): ExternalId => ExternalId::doi($value))
        ->toThrow(InvalidArgumentException::class, 'A doi must be bare or a URL starting with https://doi.org/');
})->with([
    'http' => ['http://doi.org/10.5555/12345678'],
    'another resolver host' => ['https://dx.doi.org/10.5555/12345678'],
    'a landing page' => ['https://example.org/10.5555/12345678'],
]);

it('compares the resolver\'s scheme and host case-insensitively, not its path', function (): void {
    expect(fn (): ExternalId => ExternalId::issn('https://portal.issn.org/resource/issn/1234-5679'))
        ->toThrow(InvalidArgumentException::class, 'A issn must be bare or a URL starting with https://portal.issn.org/resource/ISSN/')
        ->and(ExternalId::issn('HTTPS://PORTAL.ISSN.ORG/resource/ISSN/1234-5679')->url)
        ->toBe('HTTPS://PORTAL.ISSN.ORG/resource/ISSN/1234-5679');
});

it('sends an explicit URL unchecked, for another resolver of the same identifier', function (): void {
    $id = ExternalId::doi('10.80123/abc', url: 'https://handle.test.datacite.org/10.80123/abc');

    expect([$id->value, $id->url])->toBe(['10.80123/abc', 'https://handle.test.datacite.org/10.80123/abc'])
        ->and(ExternalId::doi('https://dx.doi.org/10.5555/x', url: 'https://dx.doi.org/10.5555/x')->url)
        ->toBe('https://dx.doi.org/10.5555/x');
});
