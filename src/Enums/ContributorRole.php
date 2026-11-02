<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * Role of a work contributor. ORCID accepts its original roles and the CRediT taxonomy (stored as URIs);
 * it says the original roles will be removed in a future API version, so prefer CRediT roles.
 */
enum ContributorRole: string
{
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

    // ORCID's original roles
    case Author = 'author';
    case Assignee = 'assignee';
    case Editor = 'editor';
    case ChairOrTranslator = 'chair-or-translator';
    case CoInvestigator = 'co-investigator';
    case CoInventor = 'co-inventor';
    case GraduateStudent = 'graduate-student';
    case OtherInventor = 'other-inventor';
    case PrincipalInvestigator = 'principal-investigator';
    case PostdoctoralResearcher = 'postdoctoral-researcher';
    case SupportStaff = 'support-staff';
}
