<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Organization;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * Persistent identifier of an organization, required by ORCID on affiliations
 * (error 9045 without it).
 */
final readonly class DisambiguatedOrganization
{
    public const string ROR = 'ROR';

    public const string RINGGOLD = 'RINGGOLD';

    public const string FUNDREF = 'FUNDREF';

    public const string GRID = 'GRID';

    public const string LEI = 'LEI';

    public function __construct(
        /** e.g. "https://ror.org/02qa1x782" for ROR */
        public string $identifier,
        /** One of the class constants, e.g. self::ROR */
        public string $source = self::ROR,
    ) {
        if (trim($identifier) === '' || trim($source) === '') {
            throw new \InvalidArgumentException('A disambiguated organization needs an identifier and a source');
        }
    }

    public static function ror(string $identifier): self
    {
        return new self($identifier, self::ROR);
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        $identifier = $data === null ? null : Json::string($data, 'disambiguated-organization-identifier');
        $source = $data === null ? null : Json::string($data, 'disambiguation-source');

        return $identifier === null || $source === null ? null : new self($identifier, $source);
    }

    /**
     * @return array{disambiguated-organization-identifier: string, disambiguation-source: string}
     */
    public function toArray(): array
    {
        return [
            'disambiguated-organization-identifier' => trim($this->identifier),
            'disambiguation-source' => $this->source,
        ];
    }
}
