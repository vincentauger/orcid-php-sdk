<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * Type of a funding: ORCID's v3.0 list. Use the funding's organization-defined type for the funder's own name for it
 * (e.g. "Discovery grant").
 */
enum FundingType: string
{
    case Award = 'award';
    case Contract = 'contract';
    case Grant = 'grant';
    case SalaryAward = 'salary-award';
}
