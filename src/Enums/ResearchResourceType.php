<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Enums;

/**
 * Type of a research resource item: ORCID's v3.0 list (an unknown one is 400, error 9047, `c4/s11`)
 */
enum ResearchResourceType: string
{
    /** E.g. a research vessel, a telescope, a facility */
    case Infrastructures = 'infrastructures';
    /** E.g. specimens, samples, a library or museum collection */
    case Collections = 'collections';
    case Equipment = 'equipment';
    /** E.g. a computing or sequencing service */
    case Services = 'services';
}
