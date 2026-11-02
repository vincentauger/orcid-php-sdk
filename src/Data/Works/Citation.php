<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Works;

use VincentAuger\OrcidSdk\Enums\CitationType;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * A work's citation, in the format given by its type (BibTeX preferred). ORCID rejects a blank value (error 9020).
 */
final readonly class Citation
{
    public string $value;

    /**
     * ORCID's citation type, kept when the SDK does not know it
     */
    public ?string $rawType;

    public function __construct(
        public ?CitationType $type,
        string $value,
        ?string $rawType = null,
    ) {
        $this->value = Json::blankToNull($value) ?? throw new \InvalidArgumentException('A citation cannot be blank');
        $this->rawType = $rawType ?? $type?->value;
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        $value = $data === null ? null : Json::string($data, 'citation-value');

        if ($data === null || $value === null) {
            return null;
        }

        return new self(
            type: Json::enum($data, 'citation-type', CitationType::class),
            value: $value,
            rawType: Json::string($data, 'citation-type'),
        );
    }

    /**
     * @return array{citation-type: string|null, citation-value: string}
     */
    public function toArray(): array
    {
        return ['citation-type' => $this->type?->value, 'citation-value' => $this->value];
    }
}
