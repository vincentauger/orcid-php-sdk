<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * The seven ORCID affiliation sections. They all share the same schema and
 * only differ by endpoint.
 */
enum AffiliationType: string
{
    case Employment = 'employment';
    case Education = 'education';
    case Qualification = 'qualification';
    case InvitedPosition = 'invited-position';
    case Distinction = 'distinction';
    case Membership = 'membership';
    case Service = 'service';

    /**
     * Endpoint segment for a single item (/{orcid}/employment/{put-code})
     */
    public function itemEndpoint(): string
    {
        return $this->value;
    }

    /**
     * Endpoint segment for the list (/{orcid}/employments)
     */
    public function listEndpoint(): string
    {
        return $this->value.'s';
    }

    /**
     * Key of an item inside a list's summaries ("employment-summary")
     */
    public function summaryKey(): string
    {
        return $this->value.'-summary';
    }
}
