<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * Where a contributor appears in a work's contributor list
 */
enum ContributorSequence: string
{
    case First = 'first';
    case Additional = 'additional';
}
