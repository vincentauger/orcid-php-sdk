<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Affiliations\Affiliation;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Enums\AffiliationType;
use VincentAuger\OrcidSdk\Exceptions\OrcidNotFoundException;

/*
 * Talks to the ORCID sandbox without fixtures (composer test:live): checks that ORCID still behaves as recorded.
 * Skipped without a .env file.
 */

it('creates, reads, updates, lists and deletes an affiliation of every type on the sandbox', function (AffiliationType $type): void {
    $this->requiresSandbox();

    $affiliations = $this->getMemberApiClient()->affiliations();
    $label = 'live '.$type->value;
    $putCode = $affiliations->create($type, testAffiliation($label));

    try {
        $created = $affiliations->get($type, $putCode);
        $updated = $affiliations->update($type, $putCode, testAffiliation($label, roleTitle: null, endDate: FuzzyDate::fromString('2023')));
        $list = $affiliations->list($type);
        $public = $this->getPublicApiClient()->record($this->orcidId())->affiliations()->get($type, $putCode);
    } finally {
        $affiliations->delete($type, $putCode);
    }

    $ours = array_values(array_filter($list, fn (Affiliation $affiliation): bool => $affiliation->putCode === $putCode));

    expect($created->type)->toBe($type)
        ->and($created->departmentName)->toBe('orcid-php-sdk '.$label)
        ->and($this->isOurs($created->source))->toBeTrue()
        // PUT replaces the whole item: the role title left out is cleared
        ->and($updated->roleTitle)->toBeNull()
        ->and((string) $updated->endDate)->toBe('2023')
        ->and($ours)->toHaveCount(1)
        ->and($public->putCode)->toBe($putCode)
        ->and(fn (): Affiliation => $affiliations->get($type, $putCode))->toThrow(OrcidNotFoundException::class);
})->with(AffiliationType::cases())->group('live');
