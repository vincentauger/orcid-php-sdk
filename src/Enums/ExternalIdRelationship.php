<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

enum ExternalIdRelationship: string
{
    case Self = 'self';
    case PartOf = 'part-of';
    case VersionOf = 'version-of';
    case FundedBy = 'funded-by';
}
