<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * Role of a funding contributor. ORCID accepts its funding roles and the CRediT taxonomy (stored as URIs), but not
 * the work roles (error 9058, `c2/f16`).
 */
enum FundingContributorRole: string
{
    // ORCID's funding roles
    case Lead = 'lead';
    case CoLead = 'co-lead';
    case SupportedBy = 'supported-by';
    case OtherContribution = 'other-contribution';

    // CRediT (https://credit.niso.org)
    case Conceptualization = 'http://credit.niso.org/contributor-roles/conceptualization/';
    case DataCuration = 'http://credit.niso.org/contributor-roles/data-curation/';
    case FormalAnalysis = 'http://credit.niso.org/contributor-roles/formal-analysis/';
    case FundingAcquisition = 'http://credit.niso.org/contributor-roles/funding-acquisition/';
    case Investigation = 'http://credit.niso.org/contributor-roles/investigation/';
    case Methodology = 'http://credit.niso.org/contributor-roles/methodology/';
    case ProjectAdministration = 'http://credit.niso.org/contributor-roles/project-administration/';
    case Resources = 'http://credit.niso.org/contributor-roles/resources/';
    case Software = 'http://credit.niso.org/contributor-roles/software/';
    case Supervision = 'http://credit.niso.org/contributor-roles/supervision/';
    case Validation = 'http://credit.niso.org/contributor-roles/validation/';
    case Visualization = 'http://credit.niso.org/contributor-roles/visualization/';
    case WritingOriginalDraft = 'http://credit.niso.org/contributor-roles/writing-original-draft/';
    case WritingReviewEditing = 'http://credit.niso.org/contributor-roles/writing-review-editing/';
}
