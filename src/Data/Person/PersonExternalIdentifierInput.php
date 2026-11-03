<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Data to create or update the researcher's identifier in another system. Unlike an activity's external id, the
 * type is free text ("Scopus Author ID", "ResearcherID", ...), the URL is required, and the relationship is always
 * `self`. ORCID does not check the URL.
 *
 * ORCID refuses a blank type or value (9047), a missing or blank URL, a type or value over 255 characters (error
 * 9020), and a type and value this client already added (409, error 9030).
 */
final readonly class PersonExternalIdentifierInput
{
    public const int MAX_LENGTH = 255;

    public string $type;

    public string $value;

    public string $url;

    public function __construct(string $type, string $value, string $url)
    {
        $this->type = Json::blankToNull($type)
            ?? throw new \InvalidArgumentException('ORCID requires a non-blank type (error 9047)');
        $this->value = Json::blankToNull($value)
            ?? throw new \InvalidArgumentException('ORCID requires a non-blank value (error 9047)');
        $this->url = Json::blankToNull($url)
            ?? throw new \InvalidArgumentException('ORCID requires a non-blank URL (error 9020)');

        foreach (['type' => $this->type, 'value' => $this->value] as $field => $text) {
            if (mb_strlen($text) > self::MAX_LENGTH) {
                throw new \InvalidArgumentException(sprintf('The external identifier %s is longer than %d characters (error 9020)', $field, self::MAX_LENGTH));
            }
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
            'external-id-type' => $this->type,
            'external-id-value' => $this->value,
            'external-id-url' => Json::wrap($this->url),
            // ORCID refuses any other relationship for a person identifier (error 9020)
            'external-id-relationship' => ExternalIdRelationship::Self->value,
        ]);
    }
}
