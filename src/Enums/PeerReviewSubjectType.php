<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * Type of the item a peer review is about: ORCID's v3.0 list (orcid-model PeerReviewSubjectType), that is every
 * work type, every funding type and a research resource proposal. ORCID also accepts "undefined", which the SDK
 * does not send.
 */
enum PeerReviewSubjectType: string
{
    case Annotation = 'annotation';
    case ArtisticPerformance = 'artistic-performance';
    case BlogPost = 'blog-post';
    case BookChapter = 'book-chapter';
    case BookReview = 'book-review';
    case Book = 'book';
    case CartographicMaterial = 'cartographic-material';
    case ClinicalStudy = 'clinical-study';
    case ConferenceAbstract = 'conference-abstract';
    case ConferenceOutput = 'conference-output';
    case ConferencePaper = 'conference-paper';
    case ConferencePoster = 'conference-poster';
    case ConferencePresentation = 'conference-presentation';
    case ConferenceProceedings = 'conference-proceedings';
    case DataManagementPlan = 'data-management-plan';
    case DataSet = 'data-set';
    case Design = 'design';
    case DictionaryEntry = 'dictionary-entry';
    case Disclosure = 'disclosure';
    case DissertationThesis = 'dissertation-thesis';
    case EditedBook = 'edited-book';
    case EncyclopediaEntry = 'encyclopedia-entry';
    case Image = 'image';
    case Invention = 'invention';
    case JournalArticle = 'journal-article';
    case JournalIssue = 'journal-issue';
    case LearningObject = 'learning-object';
    case LectureSpeech = 'lecture-speech';
    case License = 'license';
    case MagazineArticle = 'magazine-article';
    case Manual = 'manual';
    case MovingImage = 'moving-image';
    case MusicalComposition = 'musical-composition';
    case NewsletterArticle = 'newsletter-article';
    case NewspaperArticle = 'newspaper-article';
    case OnlineResource = 'online-resource';
    case Other = 'other';
    case Patent = 'patent';
    case PhysicalObject = 'physical-object';
    case Preprint = 'preprint';
    case RegisteredCopyright = 'registered-copyright';
    case Report = 'report';
    case ResearchTechnique = 'research-technique';
    case ResearchTool = 'research-tool';
    case Review = 'review';
    case Software = 'software';
    case Sound = 'sound';
    case SpinOffCompany = 'spin-off-company';
    case StandardsAndPolicy = 'standards-and-policy';
    case SupervisedStudentPublication = 'supervised-student-publication';
    case TechnicalStandard = 'technical-standard';
    case Test = 'test';
    case Trademark = 'trademark';
    case Transcription = 'transcription';
    case Translation = 'translation';
    case Website = 'website';
    case WorkingPaper = 'working-paper';
    case Award = 'award';
    case Contract = 'contract';
    case Grant = 'grant';
    case SalaryAward = 'salary-award';
    case ResearchResourceProposal = 'research-resource-proposal';
}
