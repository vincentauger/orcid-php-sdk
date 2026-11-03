<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Traits\Data;

use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Support\Json;

/**
 * Research resource inputs: the hosts of a proposal and of its items follow the same rules (`c4/s04`, `s05`,
 * `s12`, `s14`-`s16`, `s34`-`s36`)
 */
trait ValidatesHosts
{
    /**
     * @param  list<Organization>  $hosts
     * @param  string  $owner  What the hosts belong to, for the message: "proposal" or "resource item"
     */
    private function assertHosts(array $hosts, string $owner): void
    {
        if ($hosts === []) {
            throw new \InvalidArgumentException(sprintf('A %s requires a host (error 9047)', $owner));
        }

        foreach ($hosts as $host) {
            if (Json::blankToNull($host->name) === null) {
                throw new \InvalidArgumentException('ORCID requires a non-blank organization name (error 9047)');
            }

            // ORCID only requires the address element (a country alone will do), but an empty one would be sent as a JSON list
            if (Json::blankToNull($host->address?->city) === null && Json::blankToNull($host->address?->country) === null) {
                throw new \InvalidArgumentException('ORCID requires an organization address, with a city or a country (error 9047)');
            }

            if (! $host->disambiguatedOrganization instanceof DisambiguatedOrganization) {
                throw new \InvalidArgumentException('ORCID requires a disambiguated organization, e.g. a ROR identifier (error 9045)');
            }
        }
    }

    private function assertMaxLength(?string $value, int $max, string $field): void
    {
        if ($value !== null && mb_strlen($value) > $max) {
            throw new \InvalidArgumentException(sprintf('The %s is longer than %d characters', $field, $max));
        }
    }
}
