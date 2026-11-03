<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * A column of a CSV search (the `fl` parameter), in ORCID's default order. ORCID silently drops a column it doesn't
 * know (`c8/x03`), hence the enum.
 */
enum CsvSearchField: string
{
    case Orcid = 'orcid';
    /** Public emails only */
    case Email = 'email';
    case GivenNames = 'given-names';
    case FamilyName = 'family-name';
    case GivenAndFamilyNames = 'given-and-family-names';
    case CurrentInstitutionAffiliationName = 'current-institution-affiliation-name';
    case PastInstitutionAffiliationName = 'past-institution-affiliation-name';
    case CreditName = 'credit-name';
    case OtherNames = 'other-names';
}
