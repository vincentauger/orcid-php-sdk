<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data;

use VincentAuger\OrcidSdk\Enums\Scope;

/**
 * Token returned by the ORCID OAuth token endpoint.
 *
 * For 3-legged tokens, ORCID also returns the iD and the name of the
 * record owner who granted it. Client credentials tokens have neither.
 */
final readonly class OrcidToken
{
    /**
     * @param  Scope[]  $scopes
     */
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public ?string $refreshToken,
        public int $expiresIn,
        public array $scopes,
        public ?string $orcid = null,
        public ?string $name = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: self::string($data, 'access_token') ?? throw new \UnexpectedValueException('ORCID token response has no access_token'),
            tokenType: self::string($data, 'token_type') ?? 'bearer',
            refreshToken: self::string($data, 'refresh_token'),
            expiresIn: is_numeric($data['expires_in'] ?? null) ? (int) $data['expires_in'] : 0,
            scopes: Scope::decode(self::string($data, 'scope') ?? ''),
            orcid: self::string($data, 'orcid'),
            name: self::string($data, 'name'),
        );
    }

    public function hasScope(Scope $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
