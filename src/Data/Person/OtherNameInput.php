<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * Data to create or update another name the researcher is known by. ORCID stores the content as sent, blank
 * included, so it is trimmed here and a blank name is refused.
 *
 * ORCID refuses a name over 255 characters (9047) and one this client already added (409, error 9030, compared
 * case-sensitively).
 */
final readonly class OtherNameInput
{
    public const int MAX_LENGTH = 255;

    public string $content;

    public function __construct(string $content)
    {
        $this->content = Json::blankToNull($content)
            ?? throw new \InvalidArgumentException('An other name cannot be blank: ORCID would store it');

        if (mb_strlen($this->content) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('The other name is longer than %d characters (error 9047)', self::MAX_LENGTH));
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
