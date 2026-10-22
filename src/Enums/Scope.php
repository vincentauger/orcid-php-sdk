<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * ORCID OAuth scopes
 */
enum Scope: string
{
    // 3-legged (granted by the record owner)
    case Authenticate = '/authenticate';
    case OpenId = 'openid';
    case ReadLimited = '/read-limited';
    case ActivitiesUpdate = '/activities/update';
    case PersonUpdate = '/person/update';

    // 2-legged (client credentials)
    case ReadPublic = '/read-public';
    case Webhook = '/webhook';

    /**
     * Encode scopes as the space separated list ORCID expects
     *
     * @param  Scope[]  $scopes
     */
    public static function encode(array $scopes): string
    {
        return implode(' ', array_map(static fn (Scope $scope): string => $scope->value, $scopes));
    }

    /**
     * Decode a space separated list of scopes, as returned by ORCID.
     * Scopes unknown to this SDK are ignored.
     *
     * @return Scope[]
     */
    public static function decode(string $scopes): array
    {
        $parts = preg_split('/\s+/', trim($scopes), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map(self::tryFrom(...), $parts)));
    }

    /**
     * The scopes needed to read limited data and write to a record
     *
     * @return Scope[]
     */
    public static function completeAccess(): array
    {
        return [self::ReadLimited, self::ActivitiesUpdate, self::PersonUpdate];
    }
}
