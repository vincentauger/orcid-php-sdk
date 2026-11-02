<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * Who may read an item, as set by the researcher
 */
enum Visibility: string
{
    /** Everyone */
    case Public = 'public';

    /** Trusted parties: clients the researcher granted /read-limited */
    case Limited = 'limited';

    /** Signed-in ORCID users (legacy; ORCID no longer offers it) */
    case RegisteredOnly = 'registered-only';

    /** Only the researcher */
    case Private = 'private';
}
