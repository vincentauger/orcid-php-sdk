<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * Data to create or update a keyword. ORCID stores the content as sent, so it is trimmed here: "x " and "x"
 * would otherwise be two keywords.
 *
 * ORCID refuses a blank keyword (error 9020), one over 255 characters (9047) and one whose content this
 * client already added (409, error 9030, compared case-sensitively).
 */
final readonly class KeywordInput
{
    public const int MAX_LENGTH = 255;

    public string $content;

    public function __construct(string $content)
    {
        $this->content = Json::blankToNull($content)
            ?? throw new \InvalidArgumentException('ORCID requires a non-blank keyword (error 9020)');

        if (mb_strlen($this->content) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('The keyword is longer than %d characters (error 9047)', self::MAX_LENGTH));
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
            'content' => $this->content,
        ]);
    }
}
