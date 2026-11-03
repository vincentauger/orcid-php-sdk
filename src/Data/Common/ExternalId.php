<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Common;

use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * An identifier attached to an item (grant number, DOI, internal id, ...)
 *
 * The named constructors (doi(), issn(), ...) cover the common types; the constructor takes any other type ORCID knows
 * ("other-id", ...). They keep the value as sent: ORCID normalizes it itself (lowercase DOI, ...), and doesn't check
 * its format (an invalid DOI is stored with "external-id-normalized-error", c1/q04). Without a $url, the types with a
 * resolver link the value to it, using ORCID's resolver prefixes (`resolutionPrefix` in GET /v3.0/identifiers): a bare
 * value gets the prefix, a value already written as the resolver URL is its own URL, and any other URL as the value is
 * refused. An explicit $url is sent unchecked: it is for another resolver of the same identifier (e.g. DataCite's test
 * resolver, https://handle.test.datacite.org/10.80...), not a landing page, which goes in the item's own URL.
 */
final readonly class ExternalId
{
    public function __construct(
        /** ORCID identifier type, e.g. "doi", "grant_number", "proposal-id" */
        public string $type,
        public string $value,
        public ExternalIdRelationship $relationship = ExternalIdRelationship::Self,
        public ?string $url = null,
        /** Read only: ORCID's normalized form of the value (e.g. a lowercase DOI), used to group items; never sent */
        public ?string $normalizedValue = null,
    ) {
        if (trim($type) === '' || trim($value) === '') {
            throw new \InvalidArgumentException('An external id needs a type and a value');
        }
    }

    public static function doi(string $value, ExternalIdRelationship $relationship = ExternalIdRelationship::Self, ?string $url = null): self
    {
        return self::withResolver('doi', 'https://doi.org/', $value, $relationship, $url);
    }

    public static function isbn(string $value, ExternalIdRelationship $relationship = ExternalIdRelationship::Self, ?string $url = null): self
    {
        return self::withResolver('isbn', 'https://www.worldcat.org/isbn/', $value, $relationship, $url);
    }

    /**
     * Part-of by default: on a work, the ISSN is usually its journal's, not the work's own id
     */
    public static function issn(string $value, ExternalIdRelationship $relationship = ExternalIdRelationship::PartOf, ?string $url = null): self
    {
        return self::withResolver('issn', 'https://portal.issn.org/resource/ISSN/', $value, $relationship, $url);
    }

    public static function pmid(string $value, ExternalIdRelationship $relationship = ExternalIdRelationship::Self, ?string $url = null): self
    {
        return self::withResolver('pmid', 'https://pubmed.ncbi.nlm.nih.gov/', $value, $relationship, $url);
    }

    public static function pmc(string $value, ExternalIdRelationship $relationship = ExternalIdRelationship::Self, ?string $url = null): self
    {
        return self::withResolver('pmc', 'https://europepmc.org/article/pmc/', $value, $relationship, $url);
    }

    public static function arxiv(string $value, ExternalIdRelationship $relationship = ExternalIdRelationship::Self, ?string $url = null): self
    {
        return self::withResolver('arxiv', 'https://arxiv.org/abs/', $value, $relationship, $url);
    }

    public static function handle(string $value, ExternalIdRelationship $relationship = ExternalIdRelationship::Self, ?string $url = null): self
    {
        return self::withResolver('handle', 'http://hdl.handle.net/', $value, $relationship, $url);
    }

    public static function rrid(string $value, ExternalIdRelationship $relationship = ExternalIdRelationship::Self, ?string $url = null): self
    {
        return self::withResolver('rrid', 'https://identifiers.org/rrid/', $value, $relationship, $url);
    }

    /**
     * No resolver: an http(s) URI is its own URL, any other (urn:, ...) is sent without one
     */
    public static function uri(string $value, ExternalIdRelationship $relationship = ExternalIdRelationship::Self, ?string $url = null): self
    {
        $url ??= preg_match('~^https?://~i', trim($value)) === 1 ? trim($value) : null;

        return new self('uri', $value, $relationship, $url);
    }

    public static function grantNumber(string $value, ExternalIdRelationship $relationship = ExternalIdRelationship::Self, ?string $url = null): self
    {
        return new self('grant_number', $value, $relationship, $url);
    }

    public static function proposalId(string $value, ExternalIdRelationship $relationship = ExternalIdRelationship::Self, ?string $url = null): self
    {
        return new self('proposal-id', $value, $relationship, $url);
    }

    public static function sourceWorkId(string $value, ExternalIdRelationship $relationship = ExternalIdRelationship::Self, ?string $url = null): self
    {
        return new self('source-work-id', $value, $relationship, $url);
    }

    private static function withResolver(string $type, string $resolver, string $value, ExternalIdRelationship $relationship, ?string $url): self
    {
        $trimmed = trim($value);
        // scheme and host are case-insensitive, the path is not
        $hostLength = (int) strpos($resolver, '/', strlen('https://'));
        $isResolverUrl = strncasecmp($trimmed, $resolver, $hostLength) === 0
            && str_starts_with(substr($trimmed, $hostLength), substr($resolver, $hostLength));

        $url ??= match (true) {
            $isResolverUrl => $trimmed,
            preg_match('~^[a-z][a-z0-9+.-]*://~i', $trimmed) === 1 => throw new \InvalidArgumentException(
                "A {$type} must be bare or a URL starting with {$resolver}, not {$value}; pass another resolver as \$url",
            ),
            default => $resolver.$trimmed,
        };

        return new self($type, $value, $relationship, $url);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $type = Json::string($data, 'external-id-type');
        $value = Json::string($data, 'external-id-value');

        if ($type === null || $value === null) {
            return null;
        }

        return new self(
            type: $type,
            value: $value,
            relationship: Json::enum($data, 'external-id-relationship', ExternalIdRelationship::class) ?? ExternalIdRelationship::Self,
            url: Json::string($data, 'external-id-url'),
            normalizedValue: Json::string($data, 'external-id-normalized'),
        );
    }

    /**
     * Read the {"external-id": [...]} wrapper used by ORCID
     *
     * @param  array<array-key, mixed>|null  $data
     * @return list<self>
     */
    public static function listFromArray(?array $data): array
    {
        if ($data === null) {
            return [];
        }

        return array_values(array_filter(array_map(self::fromArray(...), Json::list($data, 'external-id'))));
    }

    /**
     * @param  list<self>  $externalIds
     * @return array{external-id: list<array<string, mixed>>}|null
     */
    public static function listToArray(array $externalIds): ?array
    {
        if ($externalIds === []) {
            return null;
        }

        return ['external-id' => array_map(static fn (self $id): array => $id->toArray(), $externalIds)];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::withoutNulls([
            'external-id-type' => $this->type,
            'external-id-value' => $this->value,
            'external-id-url' => Json::wrap($this->url),
            'external-id-relationship' => $this->relationship->value,
        ]);
    }
}
