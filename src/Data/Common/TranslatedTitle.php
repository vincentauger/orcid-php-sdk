<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Common;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * A title translated into another language. ORCID requires the language code (error 9047 without it).
 */
final readonly class TranslatedTitle implements \Stringable
{
    public string $title;

    public string $languageCode;

    public function __construct(
        string $title,
        /** ORCID language code, e.g. "fr" or "zh_CN" */
        string $languageCode,
    ) {
        $this->title = Json::blankToNull($title) ?? throw new \InvalidArgumentException('A translated title cannot be blank');
        $this->languageCode = Json::blankToNull($languageCode) ?? throw new \InvalidArgumentException('A translated title needs a language code');
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        $title = $data === null ? null : Json::string($data, 'value');
        $languageCode = $data === null ? null : Json::string($data, 'language-code');

        if ($title === null || $languageCode === null) {
            return null;
        }

        return new self($title, $languageCode);
    }

    /**
     * @return array{value: string, language-code: string}
     */
    public function toArray(): array
    {
        return ['value' => $this->title, 'language-code' => $this->languageCode];
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
