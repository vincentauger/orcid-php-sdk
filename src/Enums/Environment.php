<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * The ORCID environment to talk to. Each environment has three hosts:
 * the OAuth/registry host, the Member API and the Public API.
 */
enum Environment: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';

    /**
     * Host for the OAuth endpoints (authorize, token, revoke, userinfo)
     */
    public function oauthUrl(): string
    {
        return match ($this) {
            self::Production => 'https://orcid.org',
            self::Sandbox => 'https://sandbox.orcid.org',
        };
    }

    /**
     * Host for the record API, depending on the access level
     */
    public function apiUrl(ApiAccess $access): string
    {
        return match ([$this, $access]) {
            [self::Production, ApiAccess::Member] => 'https://api.orcid.org',
            [self::Production, ApiAccess::Public] => 'https://pub.orcid.org',
            [self::Sandbox, ApiAccess::Member] => 'https://api.sandbox.orcid.org',
            [self::Sandbox, ApiAccess::Public] => 'https://pub.sandbox.orcid.org',
        };
    }
}
