<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * Type of an ORCID peer review item
 */
enum PeerReviewType: string
{
    case Review = 'review';
    case Evaluation = 'evaluation';
}
