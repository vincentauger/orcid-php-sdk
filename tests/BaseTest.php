<?php

declare(strict_types=1);

namespace Tests;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use Saloon\MockConfig;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Common\Source;
use VincentAuger\OrcidSdk\Enums\Environment;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\OrcidOAuth;

class BaseTest extends TestCase
{
    protected static bool $hasSandboxCredentials = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Without a .env file (e.g. CI) tests only replay recorded fixtures
        // and must never reach ORCID.
        if (! file_exists(__DIR__.'/../.env')) {
            MockConfig::throwOnMissingFixtures();

            return;
        }

        Dotenv::createImmutable(__DIR__.'/../')->safeLoad();

        // only ever record against the sandbox
        if (($_ENV['ORCID_ENVIRONMENT'] ?? null) !== Environment::Sandbox->value) {
            self::fail('ORCID_ENVIRONMENT must be set to "sandbox" in your .env file.');
        }

        self::$hasSandboxCredentials = true;
    }

    /**
     * The ORCID iD used in tests: the sandbox record when recording,
     * the placeholder written in the fixtures otherwise.
     */
    public function orcidId(): string
    {
        return $this->env('ORCID_ORCID_ID') ?? OrcidFixture::PLACEHOLDER_ID;
    }

    /**
     * The iDs a response can carry: the placeholder once replayed from a fixture, the sandbox record's while recording
     *
     * @return list<string>
     */
    public function recordedIds(): array
    {
        return [OrcidFixture::PLACEHOLDER_ID, $this->orcidId()];
    }

    /**
     * Whether an item was added by the sandbox member client: a response replayed from a fixture carries the
     * placeholder client id, a live one (while recording) the real one
     */
    public function isOurs(?Source $source): bool
    {
        $clientId = $this->env('ORCID_CLIENT_ID');

        return $source instanceof Source
            && ($source->isClient(OrcidFixture::PLACEHOLDER_CLIENT_ID) || ($clientId !== null && $source->isClient($clientId)));
    }

    /**
     * Member API client bound to the test record, with its 3-legged token
     *
     * @param  list<Scope>|null  $scopes  The token's scopes, to check them before sending
     */
    public function getMemberApiClient(?array $scopes = null): Orcid
    {
        return Orcid::member(
            accessToken: $this->env('ORCID_ACCESS_TOKEN') ?? 'test-token',
            orcid: $this->orcidId(),
            scopes: $scopes,
            environment: Environment::Sandbox,
        );
    }

    public function getPublicApiClient(): Orcid
    {
        return Orcid::public(environment: Environment::Sandbox);
    }

    public function getOAuthClient(): OrcidOAuth
    {
        return new OrcidOAuth(
            $this->env('ORCID_CLIENT_ID') ?? 'APP-TESTCLIENT000000',
            $this->env('ORCID_CLIENT_SECRET') ?? 'test-secret',
            Environment::Sandbox,
        );
    }

    /**
     * Skip a test that must talk to the live sandbox
     */
    public function requiresSandbox(): void
    {
        if (! self::$hasSandboxCredentials) {
            $this->markTestSkipped('Requires ORCID sandbox credentials in .env');
        }
    }

    private function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
