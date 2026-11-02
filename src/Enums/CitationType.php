<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * Format of a work's citation. ORCID prefers BibTeX.
 */
enum CitationType: string
{
    case Bibtex = 'bibtex';
    case Ris = 'ris';
    case FormattedApa = 'formatted-apa';
    case FormattedChicago = 'formatted-chicago';
    case FormattedHarvard = 'formatted-harvard';
    case FormattedIeee = 'formatted-ieee';
    case FormattedMla = 'formatted-mla';
    case FormattedVancouver = 'formatted-vancouver';
    case FormattedUnspecified = 'formatted-unspecified';
}
