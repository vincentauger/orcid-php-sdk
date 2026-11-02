<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Common;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * The title of a work or funding, with an optional subtitle and translation
 */
final readonly class Title implements \Stringable
{
    public string $title;

    public ?string $subtitle;

    public function __construct(
        string $title,
        ?string $subtitle = null,
        public ?TranslatedTitle $translatedTitle = null,
    ) {
        $this->title = Json::blankToNull($title) ?? throw new \InvalidArgumentException('A title cannot be blank');
        $this->subtitle = Json::blankToNull($subtitle);
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        $title = $data === null ? null : Json::string($data, 'title');

        if ($data === null || $title === null) {
            return null;
        }

        return new self(
            title: $title,
            subtitle: Json::string($data, 'subtitle'),
            translatedTitle: TranslatedTitle::fromArray(Json::object($data, 'translated-title')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::withoutNulls([
            'title' => Json::wrap($this->title),
            'subtitle' => Json::wrap($this->subtitle),
            'translated-title' => $this->translatedTitle?->toArray(),
        ]);
    }

    public function __toString(): string
    {
        return $this->subtitle === null ? $this->title : $this->title.': '.$this->subtitle;
    }
}
