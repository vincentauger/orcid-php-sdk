# ORCID PHP SDK

A modern, framework-agnostic PHP SDK for the [ORCID API](https://info.orcid.org/documentation/) v3.0, built on
[Saloon](https://docs.saloon.dev).

> **Work in progress.** The API is not stable until `1.0`; see [PLAN.md](PLAN.md) for the roadmap.

## Requirements

- PHP 8.4+

## Installation

```bash
composer require vincentauger/orcid-php-sdk
```

## Usage

```php
use VincentAuger\OrcidSdk\Enums\Environment;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\OrcidOAuth;

// Member API, bound to the record a 3-legged token was granted on: store the token's
// access token, ORCID iD and scopes, and pass them back here
$orcid = Orcid::member(
    accessToken: $accessToken,
    orcid: '0000-0002-1825-0097',
    scopes: $scopes,              // optional: a missing scope throws MissingScopeException before sending
    environment: Environment::Sandbox,
);

// Member API without a record, e.g. a /read-public client credentials token: reads only, through record($id)
$orcid = Orcid::member(accessToken: $readPublicToken);

// Public API, ideally with a /read-public client credentials token
$orcid = Orcid::public($readPublicToken);

// OAuth endpoints (orcid.org)
$oauth = new OrcidOAuth($clientId, $clientSecret, Environment::Sandbox);
```

### Affiliations

Employments, educations, qualifications, invited positions, distinctions, memberships and services all work
the same way:

```php
use VincentAuger\OrcidSdk\Data\Affiliations\AffiliationInput;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Enums\AffiliationType;

$affiliations = Orcid::member(accessToken: $accessToken, orcid: $orcidId)->affiliations();

$input = new AffiliationInput(
    organization: new Organization(
        'Fisheries and Oceans Canada',
        new OrganizationAddress(city: 'Ottawa', country: 'CA'),
        DisambiguatedOrganization::ror('https://ror.org/02qa1x782'),
    ),
    roleTitle: 'Research Scientist',
    startDate: FuzzyDate::fromString('2021-01'),
);

$putCode = $affiliations->create(AffiliationType::Employment, $input);   // store it
$affiliations->update(AffiliationType::Employment, $putCode, $input);     // replaces the whole item
$affiliations->delete(AffiliationType::Employment, $putCode);
$employments = $affiliations->list(AffiliationType::Employment);

// find the items your client added, e.g. to sync them
$ours = array_filter($employments, fn ($employment) => $employment->source?->isClient($clientId));
```

ORCID applies the researcher's default visibility to new items; clients can't set or change it. Read classes
expose it as a `Visibility` enum.

### Works

A work needs a title, a type and an external identifier with the relationship `self` (usually its DOI), which
identifies it: ORCID refuses a second work from your client with the same one. `WorkInput` checks what ORCID
would reject before anything is sent.

```php
use VincentAuger\OrcidSdk\Data\Common\Contributor;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Common\Url;
use VincentAuger\OrcidSdk\Data\Works\WorkInput;
use VincentAuger\OrcidSdk\Enums\ContributorRole;
use VincentAuger\OrcidSdk\Enums\WorkType;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;

$works = Orcid::member(accessToken: $accessToken, orcid: $orcidId)->works();

$input = new WorkInput(
    title: new Title('Ocean acidification', subtitle: 'A review'),
    type: WorkType::JournalArticle,
    externalIds: [new ExternalId('doi', '10.5555/12345678', url: 'https://doi.org/10.5555/12345678')], // self by default
    journalTitle: 'Journal of Oceans',
    publicationDate: FuzzyDate::fromString('2021-03'),
    url: new Url('https://example.org/articles/12345678'),
    contributors: [new Contributor('Josiah Carberry', $orcidId, ContributorRole::WritingOriginalDraft)],
);

try {
    $putCode = $works->create($input);                 // store it
} catch (OrcidConflictException $e) {
    // already there: your client added a work with this DOI before
    $putCode = $e->existingPutCode() ?? throw $e;
}

$work = $works->get($putCode);
$work->title?->title;
$work->type;        // WorkType, or null for a type ORCID added later...
$work->rawType;     // ...whose value is kept here

$works->update($putCode, $input);                      // replaces the whole item
$works->delete($putCode);

Orcid::public()->record($orcidId)->works()->get($putCode); // reads only
```

ORCID groups the works from different sources that share a `self` external identifier (the same DOI from a
publisher and from your system), so `list()` returns groups. Each summary and group carries ORCID's normalized
identifiers, so `10.1/ABC` and `https://doi.org/10.1/abc` compare equal:

```php
$list = $works->list();                    // or Orcid::public()->record($orcidId)->works()->list()

foreach ($list->preferred() as $summary) { // one per group, the one ORCID displays
    echo $summary->title, PHP_EOL;
}

foreach ($list->summaries() as $summary) { // every summary: yours may not be the preferred one
    if ($summary->source?->isClient($clientId)) {
        // one of your works: $summary->putCode
    }
}

foreach ($list->groups as $group) {        // is this DOI already on the record, from any source?
    foreach ($group->externalIds as $id) {
        if ($id->type === 'doi' && $id->normalizedValue === strtolower($doi)) { // $doi: a bare DOI
            // already there
        }
    }
}
```

`createMany()` and `getMany()` send up to 100 works or put-codes in one request (`Orcid::BULK_LIMIT`). Each item
succeeds or fails on its own, so a duplicate doesn't stop the others: the failures are in the result, as
`OrcidError`s, not thrown. Above 100 items they throw an `InvalidArgumentException` before sending anything: one
call is one request, so split the batch yourself (e.g. one queue job per 100).

```php
foreach (array_chunk($inputs, Orcid::BULK_LIMIT) as $chunk) {
    $result = $works->createMany($chunk);

    foreach ($result->created as $position => $putCode) { // keyed by position in $chunk
        // store $putCode for $chunk[$position]
    }

    foreach ($result->failed as $position => $error) {
        if ($error->isDuplicate()) {
            $putCode = $error->existingPutCode();         // already there, as with create()
        } else {
            // $error->errorCode, $error->developerMessage
        }
    }
}

$read = $works->getMany([123, 456]);                  // or record($orcidId)->works()->getMany(...)
$read->works[123]->title?->title;                      // keyed by put-code
$read->failed[456] ?? null;                            // an unknown put-code (error 9034), not a 404
```

### Fundings

A funding needs a title, a type, the funder (an organization with an address and a disambiguated organization, e.g.
its ROR identifier) and an external identifier, usually the grant number. Unlike a work, the identifier may be
`part-of` or `funded-by` only, but a `self` one is what ORCID uses to group fundings and to refuse duplicates. A
start date is required whenever an end date is given. `FundingInput` checks this before anything is sent.

```php
use VincentAuger\OrcidSdk\Data\Common\Amount;
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Fundings\FundingContributor;
use VincentAuger\OrcidSdk\Data\Fundings\FundingInput;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Enums\FundingContributorRole;
use VincentAuger\OrcidSdk\Enums\FundingType;

$fundings = Orcid::member(accessToken: $accessToken, orcid: $orcidId)->fundings();

$input = new FundingInput(
    title: new Title('Ocean acidification'),                     // no subtitle on fundings
    type: FundingType::Grant,
    organization: new Organization(
        'Natural Sciences and Engineering Research Council',
        new OrganizationAddress('Ottawa', 'CA'),
        DisambiguatedOrganization::ror('https://ror.org/01h531d29'),
    ),
    externalIds: [new ExternalId('grant_number', 'RGPIN-2021-1234')], // self by default
    organizationDefinedType: 'Discovery grant',                  // the funder's own name for the type
    amount: new Amount('125000.50', 'CAD'),                       // a decimal string and an ISO 4217 currency
    startDate: FuzzyDate::fromString('2021-04'),
    endDate: FuzzyDate::fromString('2026-03'),
    contributors: [new FundingContributor('Josiah Carberry', $orcidId, FundingContributorRole::Lead)],
);

$putCode = $fundings->create($input);    // a duplicate grant number throws OrcidConflictException, as with works

$funding = $fundings->get($putCode);
$funding->amount?->value;                // "125000.5": ORCID normalizes the number
$funding->type;                          // FundingType, or null for a type ORCID added later...
$funding->rawType;                       // ...whose value is kept here

$fundings->update($putCode, $input);     // replaces the whole item
$fundings->delete($putCode);

$list = $fundings->list();               // groups, as for works: preferred() and summaries()
Orcid::public()->record($orcidId)->fundings()->get($putCode); // reads only
```

Summaries in a list carry no amount, contributors or description: read the funding by put-code for those.

### Peer reviews

A peer review belongs to a review group, usually the journal: `issn:` and its ISSN. ORCID creates an ISSN group on
first use; it only checks the check digit, not that a journal has that ISSN. Other groups (`ringgold:`,
`orcid-generated:`, ...) must already exist in ORCID's group-id registry, or ORCID answers 400 (error 9020).
A peer review also needs the reviewer's role, the review type, a review identifier (your own id for the review), the
completion date and the convening organization (who organized the review, usually the publisher) with an address.
`PeerReviewInput` checks this, the group id's shape and the ISSN's check digit, before anything is sent.

How open the review is depends on the optional fields you send:

- anonymous: the required fields only, so nothing names the reviewed item
- named subject: add the subject (`subjectExternalId`, e.g. the article's DOI, `subjectName`, `subjectContainerName`,
  `subjectType`, `subjectUrl`)
- published review: give the review its public identifier (e.g. a `doi` review identifier) and its `reviewUrl`

```php
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Data\PeerReviews\PeerReviewInput;
use VincentAuger\OrcidSdk\Enums\PeerReviewRole;
use VincentAuger\OrcidSdk\Enums\PeerReviewSubjectType;
use VincentAuger\OrcidSdk\Enums\PeerReviewType;

$peerReviews = Orcid::member(accessToken: $accessToken, orcid: $orcidId)->peerReviews();

$input = new PeerReviewInput(
    role: PeerReviewRole::Reviewer,                              // or Editor, Member, Chair, Organizer
    type: PeerReviewType::Review,                                // Evaluation: after publication
    reviewIdentifiers: [new ExternalId('source-work-id', 'review-1234')], // self by default
    completionDate: FuzzyDate::fromString('2025-03'),
    reviewGroupId: 'issn:0706-652X',
    conveningOrganization: new Organization('Canadian Science Publishing', new OrganizationAddress('Ottawa', 'CA')),
    // optional: name the reviewed item
    subjectExternalId: new ExternalId('doi', '10.1139/cjfas-2024-0001'),
    subjectType: PeerReviewSubjectType::JournalArticle,
    subjectName: new Title('Salmon in a warming ocean'),       // no subtitle: ORCID drops it
);

$putCode = $peerReviews->create($input); // a duplicate review identifier throws OrcidConflictException

$peerReview = $peerReviews->get($putCode);
$peerReviews->update($putCode, $input);  // replaces the whole item
$peerReviews->delete($putCode);

$list = $peerReviews->list();            // groups, as for works: preferred() and summaries()
$list->byReviewGroup();                  // the same groups by review group id, e.g. journal by journal
Orcid::public()->record($orcidId)->peerReviews()->get($putCode); // reads only
```

ORCID groups peer reviews by review group, then by `self` review identifier: the subject's identifier groups nothing.
Summaries in a list carry nothing about the subject: read the peer review by put-code for that.

### Research resources

For infrastructure providers (research vessels, facilities, collections, ...): a research resource records that a
researcher was granted access through a proposal, and which resource items it gave access to. ORCID requires the
proposal's title (no subtitle), at least one host and an external identifier, and at least one resource item, each
with a name, a `ResearchResourceType`, a host and an external identifier. Every host needs an address (a city or a
country) and a disambiguated organization. Any relationship will do for the identifiers; give the proposal a `self`
one, since ORCID groups research resources by it. The inputs check all this before anything is sent.

```php
use VincentAuger\OrcidSdk\Data\Common\ExternalId;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Common\Title;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceInput;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceItemInput;
use VincentAuger\OrcidSdk\Data\ResearchResources\ResearchResourceProposalInput;
use VincentAuger\OrcidSdk\Enums\ResearchResourceType;

$researchResources = Orcid::member(accessToken: $accessToken, orcid: $orcidId)->researchResources();

$host = new Organization(
    'Fisheries and Oceans Canada',
    new OrganizationAddress('Ottawa', 'CA'),
    DisambiguatedOrganization::ror('https://ror.org/02qa1x782'),
);

$input = new ResearchResourceInput(
    proposal: new ResearchResourceProposalInput(
        title: new Title('Ship time on the CCGS Amundsen'),
        hosts: [$host],                                           // who granted the access
        externalIds: [new ExternalId('proposal-id', 'AMD-2021-07')], // self by default
        startDate: FuzzyDate::fromString('2021-07'),              // an end date needs a start date
        endDate: FuzzyDate::fromString('2021-08'),
    ),
    items: [new ResearchResourceItemInput(
        name: 'CCGS Amundsen',
        type: ResearchResourceType::Infrastructures,              // or Collections, Equipment, Services
        hosts: [$host],                                           // who provides the resource
        externalIds: [new ExternalId('source-work-id', 'amundsen')],
    )],
);

$putCode = $researchResources->create($input); // a duplicate `self` proposal identifier throws OrcidConflictException

$researchResource = $researchResources->get($putCode); // ->proposal and ->items
$researchResources->update($putCode, $input);  // replaces the whole item
$researchResources->delete($putCode);

$list = $researchResources->list();            // groups, as for works: preferred() and summaries()
Orcid::public()->record($orcidId)->researchResources()->get($putCode); // reads only
```

Summaries in a list only carry the proposal: read the research resource by put-code for its items.

### Person and activities

The bound member connector reads its own record (`$orcid->person()`, `$orcid->activities()`, including limited
items with `/read-limited`). Every connector reads any record's public items through `record($id)`:

```php
$record = Orcid::public()->record('https://orcid.org/0000-0002-1825-0097'); // bare iD or URL

$person = $record->person();
$person->name?->displayName();
$person->keywords;

$activities = $record->activities();
$activities->affiliations(AffiliationType::Education);
$activities->workCount;

$record->affiliations()->list(AffiliationType::Employment); // reads only: no create, update or delete
```

Own-record calls (`affiliations()`, `works()`, `fundings()`, `peerReviews()`, `researchResources()`, `person()`, `activities()`) on a connector without an `orcid` throw a
`LogicException` before anything is sent.

### Errors

Every convenience method throws a `VincentAuger\OrcidSdk\Exceptions\OrcidException`. Use `isRetryable()` to
decide whether to try again later (rate limits, ORCID outages, connection failures):

```php
try {
    $affiliations->update(AffiliationType::Employment, $putCode, $input);
} catch (OrcidAuthException $e) {
    // 401: the token was revoked or expired; the user must authorize again
} catch (OrcidForbiddenException $e) {
    // 403: $e->isNotSource() (another client created the item) or $e->isMissingScope()
} catch (OrcidException $e) {
    if ($e->isRetryable()) {
        // retry later
    }
}
```

The SDK is stateless: it never stores or refreshes tokens, and it never retries failed requests. Your
application decides where tokens live and when a failed call should be retried (for example, in a queued job).

## Testing

```bash
composer test
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for recording fixtures against the ORCID sandbox.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
