<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use VincentAuger\OrcidSdk\Enums\ApiAccess;
use VincentAuger\OrcidSdk\Enums\Environment;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\OrcidOAuth;

it('creates an anonymous public API client by default', function (): void {
    $client = new Orcid;

    expect($client->access)->toBe(ApiAccess::Public)
        ->and($client->resolveBaseUrl())->toBe('https://pub.orcid.org')
        ->and($client->getAuthenticator())->toBeNull();
});

it('creates a member API client with a bearer token', function (): void {
    $client = Orcid::member('token');

    expect($client->access)->toBe(ApiAccess::Member)
        ->and($client->resolveBaseUrl())->toBe('https://api.orcid.org')
        ->and($client->getAuthenticator())->not->toBeNull();
});

it('throws when the member API has no token', function (): void {
    expect(fn (): Orcid => new Orcid(ApiAccess::Member))
        ->toThrow(InvalidArgumentException::class, 'An access token is required for member API access');
});

it('resolves the API hosts for each environment', function (Environment $environment, ApiAccess $access, string $url): void {
    expect($environment->apiUrl($access))->toBe($url);
})->with([
    [Environment::Production, ApiAccess::Member, 'https://api.orcid.org'],
    [Environment::Production, ApiAccess::Public, 'https://pub.orcid.org'],
    [Environment::Sandbox, ApiAccess::Member, 'https://api.sandbox.orcid.org'],
    [Environment::Sandbox, ApiAccess::Public, 'https://pub.sandbox.orcid.org'],
]);

it('uses the sandbox hosts', function (): void {
    expect(Orcid::member(accessToken: 'token', environment: Environment::Sandbox)->resolveBaseUrl())->toBe('https://api.sandbox.orcid.org')
        ->and(Orcid::public(environment: Environment::Sandbox)->resolveBaseUrl())->toBe('https://pub.sandbox.orcid.org')
        ->and(new OrcidOAuth('APP-1', 'secret', Environment::Sandbox)->resolveBaseUrl())->toBe('https://sandbox.orcid.org');
});

it('sends ORCID JSON headers', function (): void {
    $headers = Orcid::member('token')->headers()->all();

    expect($headers['Accept'])->toBe('application/vnd.orcid+json')
        ->and($headers['Content-Type'])->toBe('application/vnd.orcid+json');
});

it('defaults to 10s connect and 30s request timeouts, which can be overridden', function (): void {
    $client = Orcid::member('token');

    expect($client->getConnectTimeout())->toEqual(10)
        ->and($client->getRequestTimeout())->toEqual(30);

    $client->withTimeouts(5, 60);

    expect($client->getConnectTimeout())->toEqual(5)
        ->and($client->getRequestTimeout())->toEqual(60);
});

it('does not retry failed requests', function (): void {
    expect(Orcid::member('token')->tries)->toBeNull()
        ->and((new OrcidOAuth('APP-1', 'secret'))->tries)->toBeNull();
});

it('binds a member connector to its record', function (): void {
    $client = Orcid::member(accessToken: 'token', orcid: 'https://orcid.org/0000-0002-1825-0097');

    expect($client->orcid)->toBe('0000-0002-1825-0097')
        ->and(Orcid::member(accessToken: 'token')->orcid)->toBeNull();
});

it('refuses own-record calls on a connector without a record, before sending', function (string $method): void {
    $mockClient = new MockClient([]);

    expect(fn () => Orcid::member(accessToken: 'token')->withMockClient($mockClient)->{$method}())
        ->toThrow(LogicException::class, "Orcid::{$method}() needs a connector bound to a record")
        ->and(fn () => Orcid::public()->withMockClient($mockClient)->{$method}())
        ->toThrow(LogicException::class, "Orcid::{$method}() needs a connector bound to a record");

    $mockClient->assertNothingSent();
})->with(['affiliations', 'keywords', 'person', 'biography', 'emails', 'personalDetails', 'activities']);

it('only binds member connectors to a record', function (): void {
    new Orcid(ApiAccess::Public, orcid: '0000-0002-1825-0097');
})->throws(InvalidArgumentException::class, 'Only a member API connector can be bound to a record');
