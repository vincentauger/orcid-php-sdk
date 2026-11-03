<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Common;

use VincentAuger\OrcidSdk\Enums\ContributorRole;
use VincentAuger\OrcidSdk\Enums\ContributorSequence;
use VincentAuger\OrcidSdk\Support\Json;
use VincentAuger\OrcidSdk\Support\OrcidId;

/**
 * A contributor to a work: a credit name and, when it was authenticated, their ORCID iD. Every field is
 * optional (ORCID accepts a contributor with none, `c1/p24`). Fundings have their own roles and no sequence:
 * see FundingContributor.
 *
 * Read items keep ORCID's raw role and sequence next to the enums, so a value ORCID adds later is not lost;
 * input classes only send the enums.
 */
final readonly class Contributor
{
    /**
     * The normalised ORCID iD
     */
    public ?string $orcid;

    public ?string $creditName;

    public ?string $rawRole;

    public ?string $rawSequence;

    /**
     * @param  string|null  $orcid  Bare iD or ORCID URL; only add it if the contributor authenticated it
     *
     * @throws \InvalidArgumentException when $orcid is not a valid ORCID iD
     */
    public function __construct(
        ?string $creditName = null,
        ?string $orcid = null,
        public ?ContributorRole $role = null,
        public ?ContributorSequence $sequence = null,
        ?string $rawRole = null,
        ?string $rawSequence = null,
    ) {
        $this->creditName = Json::blankToNull($creditName);
        $this->orcid = Json::blankToNull($orcid) === null ? null : OrcidId::normalize($orcid ?? '');
        $this->rawRole = $rawRole ?? $role?->value;
        $this->rawSequence = $rawSequence ?? $sequence?->value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $orcid = Json::string(Json::object($data, 'contributor-orcid') ?? [], 'path');
        $attributes = Json::object($data, 'contributor-attributes') ?? [];

        return new self(
            creditName: Json::string($data, 'credit-name'),
            orcid: $orcid !== null && OrcidId::isValid($orcid) ? $orcid : null,
            role: Json::enum($attributes, 'contributor-role', ContributorRole::class),
            sequence: Json::enum($attributes, 'contributor-sequence', ContributorSequence::class),
            rawRole: Json::string($attributes, 'contributor-role'),
            rawSequence: Json::string($attributes, 'contributor-sequence'),
        );
    }

    /**
     * Read the {"contributor": [...]} wrapper used by ORCID
     *
     * @param  array<array-key, mixed>|null  $data
     * @return list<self>
     */
    public static function listFromArray(?array $data): array
    {
        return array_map(self::fromArray(...), $data === null ? [] : Json::list($data, 'contributor'));
    }

    /**
     * @param  list<self>  $contributors
     * @return array{contributor: list<array<string, mixed>>}|null
     */
    public static function listToArray(array $contributors): ?array
    {
        if ($contributors === []) {
            return null;
        }

        return ['contributor' => array_map(static fn (self $contributor): array => $contributor->toArray(), $contributors)];
    }

    /**
     * Only the enums are sent: an unknown raw role or sequence is left out
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $attributes = Json::withoutNulls([
            'contributor-sequence' => $this->sequence?->value,
            'contributor-role' => $this->role?->value,
        ]);

        return Json::withoutNulls([
            'contributor-orcid' => $this->orcid === null ? null : ['path' => $this->orcid],
            'credit-name' => Json::wrap($this->creditName),
            'contributor-attributes' => $attributes === [] ? null : $attributes,
        ]);
    }
}
