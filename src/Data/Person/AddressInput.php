<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Person;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * Data to create or update a country on a record: ORCID only stores the country of an address. ORCID refuses a code
 * it doesn't know, a lowercase one and an alpha-3 one (9047): the code is uppercased here and its form checked, not
 * whether the country exists. It accepts a country
 * already listed, even one this client added.
 */
final readonly class AddressInput
{
    /** ISO 3166-1 alpha-2 code, e.g. "CA" */
    public string $country;

    public function __construct(string $country)
    {
        $this->country = strtoupper(trim($country));

        if (preg_match('/^[A-Z]{2}$/', $this->country) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid country "%s", expected an ISO 3166-1 alpha-2 code (error 9047)', $country));
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
            'country' => Json::wrap($this->country),
        ]);
    }
}
