<?php

declare(strict_types=1);

use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\TranslatedTitle;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResource;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceGroups;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceInput;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceItemInput;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceProposalInput;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceSummary;
use VincentAuger\OrcidSdk\Enums\ExternalIdRelationship;
use VincentAuger\OrcidSdk\Enums\ResearchResourceType;

function resourceHost(): Organization
{
    return new Organization('Fisheries and Oceans Canada', new OrganizationAddress('Ottawa', 'CA'), DisambiguatedOrganization::ror('https://ror.org/02qa1x782'));
}

/**
 * A valid proposal, with the given fields changed
 */
function proposal(mixed ...$fields): ResearchResourceProposalInput
{
    return new ResearchResourceProposalInput(...[
        'title' => new Title('Ship time on the CCGS Amundsen'),
        'hosts' => [resourceHost()],
        'externalIds' => [ExternalId::proposalId('AMD-2021-07')],
        ...$fields,
    ]);
}

/**
 * A valid resource item, with the given fields changed
 */
function resourceItem(mixed ...$fields): ResearchResourceItemInput
{
    return new ResearchResourceItemInput(...[
        'name' => 'CCGS Amundsen',
        'type' => ResearchResourceType::Infrastructures,
        'hosts' => [resourceHost()],
        'externalIds' => [ExternalId::sourceWorkId('amundsen')],
        ...$fields,
    ]);
}

const RESOURCE_HOST_BODY = [
    'name' => 'Fisheries and Oceans Canada',
    'address' => ['city' => 'Ottawa', 'country' => 'CA'],
    'disambiguated-organization' => ['disambiguated-organization-identifier' => 'https://ror.org/02qa1x782', 'disambiguation-source' => 'ROR'],
];

describe('ResearchResourceInput', function (): void {
    it('sends a minimal research resource', function (): void {
        $input = new ResearchResourceInput(
            proposal: new ResearchResourceProposalInput(
                title: new Title('Ship time on the CCGS Amundsen'),
                hosts: [resourceHost()],
                externalIds: [ExternalId::proposalId('AMD-2021-07')],
            ),
            items: [new ResearchResourceItemInput(
                name: 'CCGS Amundsen',
                type: ResearchResourceType::Infrastructures,
                hosts: [resourceHost()],
                externalIds: [ExternalId::sourceWorkId('amundsen')],
            )],
        );

        expect($input->toArray())->toBe([
            'proposal' => [
                'title' => ['title' => ['value' => 'Ship time on the CCGS Amundsen']],
                'hosts' => ['organization' => [RESOURCE_HOST_BODY]],
                'external-ids' => ['external-id' => [[
                    'external-id-type' => 'proposal-id',
                    'external-id-value' => 'AMD-2021-07',
                    'external-id-relationship' => 'self',
                ]]],
            ],
            'resource-item' => [[
                'resource-name' => 'CCGS Amundsen',
                'resource-type' => 'infrastructures',
                'hosts' => ['organization' => [RESOURCE_HOST_BODY]],
                'external-ids' => ['external-id' => [[
                    'external-id-type' => 'source-work-id',
                    'external-id-value' => 'amundsen',
                    'external-id-relationship' => 'self',
                ]]],
            ]],
        ]);
    });

    it('sends every field, in ORCID schema order', function (): void {
        $input = new ResearchResourceInput(
            proposal: new ResearchResourceProposalInput(
                title: new Title('Ship time on the CCGS Amundsen', translatedTitle: new TranslatedTitle('Temps navire', 'fr')),
                hosts: [resourceHost(), new Organization('Amundsen Science', new OrganizationAddress('Québec', 'ca', 'QC'), DisambiguatedOrganization::ror('https://ror.org/04sjchr03'))],
                externalIds: [
                    ExternalId::proposalId('AMD-2021-07', url: 'https://example.org/proposals/7'),
                    ExternalId::proposalId('AMD-2021', ExternalIdRelationship::PartOf),
                ],
                startDate: FuzzyDate::fromString('2021-07-01'),
                endDate: FuzzyDate::fromString('2021-08'),
                url: new Url('https://example.org/proposals/7'),
            ),
            items: [
                resourceItem(url: new Url('https://example.org/amundsen')),
                resourceItem(name: 'Rosette sampler', type: ResearchResourceType::Equipment, externalIds: [ExternalId::sourceWorkId('rosette', ExternalIdRelationship::PartOf)]),
            ],
        );

        expect($input->toArray(1234))->toBe([
            'put-code' => 1234,
            'proposal' => [
                'title' => [
                    'title' => ['value' => 'Ship time on the CCGS Amundsen'],
                    'translated-title' => ['value' => 'Temps navire', 'language-code' => 'fr'],
                ],
                'hosts' => ['organization' => [
                    RESOURCE_HOST_BODY,
                    [
                        'name' => 'Amundsen Science',
                        'address' => ['city' => 'Québec', 'region' => 'QC', 'country' => 'CA'],
                        'disambiguated-organization' => ['disambiguated-organization-identifier' => 'https://ror.org/04sjchr03', 'disambiguation-source' => 'ROR'],
                    ],
                ]],
                'external-ids' => ['external-id' => [
                    [
                        'external-id-type' => 'proposal-id',
                        'external-id-value' => 'AMD-2021-07',
                        'external-id-url' => ['value' => 'https://example.org/proposals/7'],
                        'external-id-relationship' => 'self',
                    ],
                    [
                        'external-id-type' => 'proposal-id',
                        'external-id-value' => 'AMD-2021',
                        'external-id-relationship' => 'part-of',
                    ],
                ]],
                'start-date' => ['year' => ['value' => '2021'], 'month' => ['value' => '07'], 'day' => ['value' => '01']],
                'end-date' => ['year' => ['value' => '2021'], 'month' => ['value' => '08']],
                'url' => ['value' => 'https://example.org/proposals/7'],
            ],
            'resource-item' => [
                [
                    'resource-name' => 'CCGS Amundsen',
                    'resource-type' => 'infrastructures',
                    'hosts' => ['organization' => [RESOURCE_HOST_BODY]],
                    'external-ids' => ['external-id' => [[
                        'external-id-type' => 'source-work-id',
                        'external-id-value' => 'amundsen',
                        'external-id-relationship' => 'self',
                    ]]],
                    'url' => ['value' => 'https://example.org/amundsen'],
                ],
                [
                    'resource-name' => 'Rosette sampler',
                    'resource-type' => 'equipment',
                    'hosts' => ['organization' => [RESOURCE_HOST_BODY]],
                    'external-ids' => ['external-id' => [[
                        'external-id-type' => 'source-work-id',
                        'external-id-value' => 'rosette',
                        'external-id-relationship' => 'part-of',
                    ]]],
                ],
            ],
        ]);
    });

    it('takes external identifiers of any relationship, a start date alone and a host with a country only (c4/s07, s23, s16)', function (): void {
        $input = new ResearchResourceInput(
            proposal(
                hosts: [new Organization('DFO', new OrganizationAddress(null, 'CA'), DisambiguatedOrganization::ror('https://ror.org/02qa1x782'))],
                externalIds: [ExternalId::proposalId('AMD-2021', ExternalIdRelationship::PartOf)],
                startDate: FuzzyDate::fromString('2021'),
            ),
            [resourceItem(externalIds: [ExternalId::sourceWorkId('amundsen', ExternalIdRelationship::PartOf)])],
        );

        expect($input->toArray()['proposal'])->toHaveKey('start-date')
            ->not->toHaveKey('end-date')
            ->and($input->toArray()['proposal']['hosts']['organization'][0]['address'])->toBe(['country' => 'CA']);
    });

    it('trims the resource name', function (): void {
        expect(resourceItem(name: ' CCGS Amundsen ')->toArray()['resource-name'])->toBe('CCGS Amundsen');
    });

    it('refuses what ORCID would reject, before sending', function (Closure $build, string $message): void {
        expect($build)->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'no resource item (9047, c4/s08, s32)' => [fn (): ResearchResourceInput => new ResearchResourceInput(proposal(), []), 'resource item'],
        'no proposal host (9047, c4/s04, s05)' => [fn (): ResearchResourceProposalInput => proposal(hosts: []), 'proposal requires a host'],
        'no proposal external identifier (9047, c4/s06)' => [fn (): ResearchResourceProposalInput => proposal(externalIds: []), 'proposal requires an external identifier'],
        'a proposal host without disambiguated organization (9045, c4/s14)' => [fn (): ResearchResourceProposalInput => proposal(hosts: [new Organization('DFO', new OrganizationAddress('Ottawa', 'CA'))]), 'disambiguated organization'],
        'a proposal host without address (9047, c4/s15)' => [fn (): ResearchResourceProposalInput => proposal(hosts: [new Organization('DFO', disambiguatedOrganization: DisambiguatedOrganization::ror('https://ror.org/02qa1x782'))]), 'address'],
        'a proposal host with neither city nor country' => [fn (): ResearchResourceProposalInput => proposal(hosts: [new Organization('DFO', new OrganizationAddress(' ', null), DisambiguatedOrganization::ror('https://ror.org/02qa1x782'))]), 'address'],
        'a proposal host with a blank name' => [fn (): ResearchResourceProposalInput => proposal(hosts: [new Organization(' ', new OrganizationAddress('Ottawa', 'CA'), DisambiguatedOrganization::ror('https://ror.org/02qa1x782'))]), 'organization name'],
        'end date without a start date (9050, c4/s17)' => [fn (): ResearchResourceProposalInput => proposal(endDate: FuzzyDate::fromString('2024')), 'start date'],
        'end date before the start date (accepted by ORCID, c4/s18)' => [fn (): ResearchResourceProposalInput => proposal(startDate: FuzzyDate::fromString('2024'), endDate: FuzzyDate::fromString('2021')), 'before the start date'],
        'a subtitle (9047, c4/s19)' => [fn (): ResearchResourceProposalInput => proposal(title: new Title('Ship time', 'On the Amundsen')), 'subtitle'],
        'title over 1000 characters (9047, c4/s25)' => [fn (): ResearchResourceProposalInput => proposal(title: new Title(str_repeat('x', 1001))), 'title is longer than 1000'],
        'translated title over 1000 characters' => [fn (): ResearchResourceProposalInput => proposal(title: new Title('Ship time', translatedTitle: new TranslatedTitle(str_repeat('é', 1001), 'fr'))), 'translated title is longer than 1000'],
        'translated title with a malformed language (9047, c4/s33)' => [fn (): ResearchResourceProposalInput => proposal(title: new Title('Ship time', translatedTitle: new TranslatedTitle('Temps navire', 'french'))), 'language code'],
        'a blank item name (9047, c4/s24)' => [fn (): ResearchResourceItemInput => resourceItem(name: ' '), 'resource name'],
        'item name over 1000 characters (9047, c4/s26)' => [fn (): ResearchResourceItemInput => resourceItem(name: str_repeat('x', 1001)), 'resource name is longer than 1000'],
        'no item host (9047, c4/s12, s36)' => [fn (): ResearchResourceItemInput => resourceItem(hosts: []), 'resource item requires a host'],
        'no item external identifier (9047, c4/s13)' => [fn (): ResearchResourceItemInput => resourceItem(externalIds: []), 'resource item requires an external identifier'],
        'an item host without disambiguated organization (9045, c4/s34)' => [fn (): ResearchResourceItemInput => resourceItem(hosts: [new Organization('DFO', new OrganizationAddress('Ottawa', 'CA'))]), 'disambiguated organization'],
        'an item host without address (9047, c4/s35)' => [fn (): ResearchResourceItemInput => resourceItem(hosts: [new Organization('DFO', disambiguatedOrganization: DisambiguatedOrganization::ror('https://ror.org/02qa1x782'))]), 'address'],
    ]);
});

describe('ResearchResource', function (): void {
    it('keeps values unknown to the SDK', function (): void {
        $resource = ResearchResource::fromArray([
            'put-code' => 42,
            'proposal' => [
                'title' => ['title' => ['value' => 'Ship time'], 'translated-title' => null],
                'hosts' => ['organization' => [['name' => 'DFO', 'address' => null], ['address' => ['city' => 'Ottawa']]]],
                'external-ids' => ['external-id' => [['external-id-type' => 'proposal-id', 'external-id-value' => 'AMD-1']]],
                'start-date' => null,
                'url' => null,
            ],
            'resource-item' => [
                ['resource-name' => 'CCGS Amundsen', 'resource-type' => 'vessels', 'hosts' => null, 'external-ids' => null, 'url' => null],
                ['resource-type' => 'services'],
            ],
            'display-index' => '1',
            'visibility' => 'public',
        ]);

        expect($resource->putCode)->toBe(42)
            ->and($resource->proposal?->title?->title)->toBe('Ship time')
            // a host without a name is skipped
            ->and($resource->proposal?->hosts)->toHaveCount(1)
            ->and($resource->proposal?->hosts[0]->name)->toBe('DFO')
            ->and($resource->proposal?->externalIds[0]->value)->toBe('AMD-1')
            ->and($resource->proposal?->startDate)->toBeNull()
            ->and($resource->items)->toHaveCount(2)
            ->and($resource->items[0]->name)->toBe('CCGS Amundsen')
            ->and($resource->items[0]->type)->toBeNull()
            ->and($resource->items[0]->rawType)->toBe('vessels')
            ->and($resource->items[0]->hosts)->toBe([])
            ->and($resource->items[0]->externalIds)->toBe([])
            ->and($resource->items[1]->name)->toBeNull()
            ->and($resource->items[1]->type)->toBe(ResearchResourceType::Services)
            ->and($resource->displayIndex)->toBe(1);
    });

    it('reads a research resource without proposal or items', function (): void {
        $resource = ResearchResource::fromArray(['put-code' => 42]);

        expect($resource->proposal)->toBeNull()
            ->and($resource->items)->toBe([]);
    });
});

describe('ResearchResourceGroups', function (): void {
    // hand-built: the sandbox only holds research resources from our client, each in its own group
    it('prefers the highest display index, the first one listed on a tie', function (): void {
        $list = ResearchResourceGroups::fromArray(['group' => [
            ['external-ids' => ['external-id' => [['external-id-type' => 'proposal-id', 'external-id-value' => 'AMD-1']]], 'research-resource-summary' => [
                ['put-code' => 1, 'display-index' => '0', 'proposal' => ['title' => ['title' => ['value' => 'Ship time']]]],
                ['put-code' => 2, 'display-index' => '3'],
                ['put-code' => 3, 'display-index' => '1'],
            ]],
            ['external-ids' => ['external-id' => []], 'research-resource-summary' => [['put-code' => 4, 'display-index' => '0'], ['put-code' => 5, 'display-index' => '0']]],
            ['research-resource-summary' => []],
        ]]);

        expect($list->groups)->toHaveCount(2)
            ->and($list->groups[0]->externalIds[0]->value)->toBe('AMD-1')
            ->and($list->groups[1]->externalIds)->toBe([])
            ->and(array_map(fn (ResearchResourceSummary $summary): int => $summary->putCode, $list->preferred()))->toBe([2, 4])
            ->and(array_map(fn (ResearchResourceSummary $summary): int => $summary->putCode, $list->summaries()))->toBe([1, 2, 3, 4, 5])
            ->and($list->groups[0]->summaries[0]->proposal?->title?->title)->toBe('Ship time')
            ->and($list->groups[0]->summaries[1]->proposal)->toBeNull();
    });

    it('reads a record without research resources', function (): void {
        $list = ResearchResourceGroups::fromArray(['last-modified-date' => null, 'group' => [], 'path' => '/0000-0002-1825-0097/research-resources']);

        expect($list->groups)->toBe([])
            ->and($list->preferred())->toBe([])
            ->and($list->summaries())->toBe([]);
    });
});
