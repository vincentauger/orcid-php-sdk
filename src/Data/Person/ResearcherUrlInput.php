<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * Data to create or update a website on a record. ORCID does not check the URL ("not a url", "example.org" and
 * "ftp://..." are stored as sent), so neither does the SDK.
 *
 * ORCID refuses a blank URL or one over 2000 characters (error 9020), a blank name or one over 350 characters
 * (9047), and a URL this client already added (409, error 9030, compared case-sensitively; names may repeat).
 */
final readonly class ResearcherUrlInput
{
    public const int MAX_URL_LENGTH = 2000;

    public const int MAX_NAME_LENGTH = 350;

    public string $url;

    public ?string $name;

    public function __construct(string $url, ?string $name = null)
    {
        $this->url = Json::blankToNull($url)
            ?? throw new \InvalidArgumentException('ORCID requires a non-blank URL (error 9020)');
        $this->name = Json::blankToNull($name);

        if (mb_strlen($this->url) > self::MAX_URL_LENGTH) {
            throw new \InvalidArgumentException(sprintf('The URL is longer than %d characters (error 9020)', self::MAX_URL_LENGTH));
        }

        if ($this->name !== null && mb_strlen($this->name) > self::MAX_NAME_LENGTH) {
            throw new \InvalidArgumentException(sprintf('The URL name is longer than %d characters (error 9047)', self::MAX_NAME_LENGTH));
        }
    }

    /**
     * ORCID JSON body. For an update, pass the item's put-code.
     *
     * @return array<string, mixed>
     */
    public function toArray(?int $putCode = null): array
    {
        return Json::withoutNulls([
            'put-code' => $putCode,
            'url-name' => $this->name,
            'url' => Json::wrap($this->url),
        ]);
    }
}
