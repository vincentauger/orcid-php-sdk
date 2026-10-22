<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data;

/**
 * OpenID Connect user info returned by /oauth/userinfo
 */
final readonly class UserInfo
{
    public function __construct(
        /** The ORCID iD, e.g. 0000-0002-1825-0097 */
        public string $orcid,
        /** The ORCID iD as a URL, e.g. https://orcid.org/0000-0002-1825-0097 */
        public string $orcidUrl,
        public ?string $name = null,
        public ?string $givenName = null,
        public ?string $familyName = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $orcid = self::string($data, 'sub') ?? throw new \UnexpectedValueException('ORCID user info has no sub');

        return new self(
            orcid: $orcid,
            orcidUrl: self::string($data, 'id') ?? 'https://orcid.org/'.$orcid,
            name: self::string($data, 'name'),
            givenName: self::string($data, 'given_name'),
            familyName: self::string($data, 'family_name'),
        );
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
