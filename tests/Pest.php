<?php

declare(strict_types=1);

use Tests\BaseTest;
use Tests\Fixtures\OrcidFixture;
use VincentAuger\OrcidSdk\Data\Affiliations\AffiliationInput;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;

pest()->extend(BaseTest::class);

/**
 * An affiliation for tests: its department name starts with the test marker, and it has its own
 * `self` external identifier, so ORCID never groups it with another test item.
 */
function testAffiliation(
    string $label,
    ?string $roleTitle = 'Tester',
    ?FuzzyDate $endDate = null,
    string $ror = 'https://ror.org/02qa1x782',
): AffiliationInput {
    return new AffiliationInput(
        organization: new Organization(
            'Fisheries and Oceans Canada',
            new OrganizationAddress('Ottawa', 'CA', 'ON'),
            DisambiguatedOrganization::ror($ror),
        ),
        departmentName: OrcidFixture::TEST_MARKER.' '.$label,
        roleTitle: $roleTitle,
        startDate: FuzzyDate::fromString('2021-01-05'),
        endDate: $endDate,
        externalIds: [new ExternalId('grant_number', OrcidFixture::TEST_MARKER.'-'.str_replace(' ', '-', $label))],
    );
}
