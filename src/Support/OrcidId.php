<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Support;

/**
 * Normalises and validates ORCID iDs.
 */
final class OrcidId
{
    /**
     * Return the bare iD (0000-0002-1825-0097) from a bare iD or an ORCID URL
     * (https://orcid.org/..., https://sandbox.orcid.org/...).
     *
     * @throws \InvalidArgumentException when the value is not a valid ORCID iD
     */
    public static function normalize(string $value): string
    {
        if (preg_match('/(\d{4}-\d{4}-\d{4}-\d{3}[\dX])\/?$/i', trim($value), $matches) !== 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is not an ORCID iD', $value));
        }

        $orcid = strtoupper($matches[1]);

        if (! self::hasValidChecksum($orcid)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid ORCID iD (bad checksum)', $value));
        }

        return $orcid;
    }

    public static function isValid(string $value): bool
    {
        try {
            self::normalize($value);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * ISO 7064 MOD 11-2 check digit, as used by ORCID
     */
    private static function hasValidChecksum(string $orcid): bool
    {
        $digits = str_replace('-', '', $orcid);
        $total = 0;

        for ($i = 0; $i < 15; $i++) {
            $total = ($total + (int) $digits[$i]) * 2;
        }

        $result = (12 - ($total % 11)) % 11;
        $check = $result === 10 ? 'X' : (string) $result;

        return $digits[15] === $check;
    }
}
