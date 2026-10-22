<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * Role of the reviewer in an ORCID peer review item
 */
enum PeerReviewRole: string
{
    case Chair = 'chair';
    case Editor = 'editor';
    case Member = 'member';
    case Organizer = 'organizer';
    case Reviewer = 'reviewer';
}
