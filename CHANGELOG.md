# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Orcid::member(accessToken:, orcid:, scopes:, environment:)`: a member connector bound to its record, with
  `affiliations()`, `person()` and `activities()` for that record (ADR 0003)
- Works: `$orcid->works()->get|create|update|delete` on a bound member connector, `record($id)->works()->get()`
  on any connector. `Work` (read) / `WorkInput` (write, validated before sending: a `self` external identifier,
  title, type, lengths, codes), `Citation`; enums `WorkType`, `CitationType`, `ContributorRole` (original and
  CRediT roles), `ContributorSequence`
- Works lists: `$orcid->works()->list()` and `record($id)->works()->list()` return `WorkGroups`, ORCID's groups
  (`WorkGroup`: external ids and `WorkSummary` items), with `preferred()` (one summary per group, what ORCID
  displays) and `summaries()` (every summary, for syncing) (ADR 0001)
- Works in bulk, at most `Orcid::BULK_LIMIT` (100) per call, above which an `InvalidArgumentException` is thrown
  before sending (ADR 0002): `getMany(putCodes)` returns a `BulkRead` (works and failures keyed by put-code), also
  on `record($id)->works()`; `createMany(inputs)` returns a `BulkResult` (put-codes and failures keyed by input
  position, `hasFailures()`). Item failures are `OrcidError`s (`isDuplicate()`, `existingPutCode()`)
- Fundings: `$orcid->fundings()->list|get|create|update|delete` on a bound member connector,
  `record($id)->fundings()->list|get` on any connector. `Funding` (read) / `FundingInput` (write, validated before
  sending: an external identifier, the funder's address and disambiguated organization, a start date with an end
  date, no subtitle, lengths, roles), `FundingSummary`, `FundingGroup`, `FundingGroups`, `FundingContributor`;
  enums `FundingType`, `FundingContributorRole` (ORCID's funding roles and CRediT)
- Peer reviews: `$orcid->peerReviews()->list|get|create|update|delete` on a bound member connector,
  `record($id)->peerReviews()->list|get` on any connector. `PeerReview` (read) / `PeerReviewInput` (write, validated
  before sending: a review identifier, the convening organization's address, the review group id's shape and an ISSN's
  check digit, no subject subtitle, lengths), `PeerReviewSummary`, `PeerReviewGroup` (with its review group id),
  `PeerReviewGroups` (`byReviewGroup()`); enum `PeerReviewSubjectType` (work and funding types, and
  `research-resource-proposal`)
- Research resources: `$orcid->researchResources()->list|get|create|update|delete` on a bound member connector,
  `record($id)->researchResources()->list|get` on any connector. `ResearchResource` (read: a
  `ResearchResourceProposal` and `ResearchResourceItem`s) / `ResearchResourceInput` (write, with
  `ResearchResourceProposalInput` and `ResearchResourceItemInput`, validated before sending: a host with an address and
  a disambiguated organization, an external identifier, at least one item, a start date with an end date, no subtitle,
  lengths), `ResearchResourceSummary`, `ResearchResourceGroup`, `ResearchResourceGroups`; enum `ResearchResourceType`;
  `Organization::listFromArray()` / `listToArray()`
- Keywords: `$orcid->keywords()->list|get|create|update|delete` on a bound member connector (writes need
  `/person/update`), `record($id)->keywords()->list|get` on any connector. `Keyword` (read, the same class as
  `Person::$keywords`) / `KeywordInput` (write, validated before sending: non-blank, at most 255 characters, trimmed)
- Other names, researcher URLs, external identifiers and addresses: `$orcid->otherNames()`, `researcherUrls()`,
  `externalIdentifiers()` and `addresses()` with `list|get|create|update|delete` on a bound member connector (writes
  need `/person/update`), and `list|get` on `record($id)`, returning the same items as `Person`. `OtherNameInput`
  (non-blank, at most 255 characters, trimmed), `ResearcherUrlInput` (URL non-blank, at most 2000 characters; name
  optional, at most 350), `PersonExternalIdentifierInput` (type, value and URL required, at most 255 characters, always
  `self`), `AddressInput` (ISO 3166-1 alpha-2 country, uppercased)
- Read-only person sections, on a bound member connector and on `record($id)`: `biography()` (`?Biography`),
  `emails()` (`list<Email>`, limited ones with `/read-limited`) and `personalDetails()` (`PersonalDetails`: name,
  other names and biography), returning the same classes as `person()`
- Whole record and record summary, on a bound member connector and on `record($id)`: `wholeRecord()` (`Record`: the
  same `Person` and `ActivitiesSummary` as `person()` and `activities()`, with `RecordHistory` and the locale) and
  `recordSummary()` (`RecordSummary`, Member API only: `ActivityCounts` per activity section, validated or
  self-asserted, `RecordSummaryAffiliation` and `RecordSummaryExternalIdentifier` items)
- `ActivitiesSummary` has typed `works` (`WorkGroups`), `fundings` (`FundingGroups`), `peerReviews`
  (`PeerReviewGroups`) and `researchResources` (`ResearchResourceGroups`), the same classes as each section's `list()`
- Typed person items, each with its put-code, visibility, source and display index: `Keyword`, `OtherName`,
  `ResearcherUrl`, `Address`, `PersonExternalIdentifier`; and `Biography`
- `OrcidConflictException::DUPLICATE_CONTENT` (error 9030, a person item with the same content: a keyword
  or other name, a website's URL, an external identifier's type and value), covered by
  `isDuplicate()`
- Shared `Title`, `TranslatedTitle`, `Contributor` and `Url` classes, and `Amount` (a decimal string and an ISO 4217
  currency)
- `OrcidConflictException::isDuplicate()` (error 9021) and `existingPutCode()`
- `Visibility` enum, `Source` assertion-origin fields, `ExternalId::$normalizedValue`, `Affiliation::$displayIndex`,
  `Email::$source`
- Search, on every connector: `search($query, start, rows)` (`SearchResults`: the iDs and `numFound`),
  `expandedSearch()` (`ExpandedSearchResults` of `ExpandedSearchResult`: names, public emails, institutions) and
  `csvSearch($query, fields)` (the CSV string; enum `CsvSearchField`). The query is raw Solr; a blank query, a
  negative `start` or `rows` outside 0 to `Orcid::SEARCH_ROWS_LIMIT` (1000) throws before sending

### Changed

- Requires Saloon `^4.5` (was `^4.0`)
- **Breaking:** `Orcid::member()` takes `orcid` and `scopes` before `environment`; use named arguments
- **Breaking:** `record($id)` only reads: `record($id)->affiliations()` returns a `ReadOnlyAffiliationsResource`
  (`list()`, `get()`). Write through the bound connector: `Orcid::member(accessToken: ..., orcid: ...)->affiliations()`
- **Breaking:** read classes expose `visibility` as a `Visibility` enum instead of a string
- **Breaking:** `Person` returns typed items instead of strings: `otherNames` (`OtherName`), `keywords`
  (`Keyword`), `biography` (`Biography`), `externalIdentifiers` (`PersonExternalIdentifier`, the identifier under
  `externalId`); `countries` is replaced by `addresses` (`Address`, with `country`); `ResearcherUrl` has a put-code
- **Breaking:** `ActivitiesSummary`'s counts are methods: `workCount()`, `fundingCount()`, `peerReviewCount()` (review
  groups, as before), `researchResourceCount()`. A group without summaries is no longer counted

## [0.1.0] - 2026-10-22

### Added

- `Orcid` connector for the Public and Member APIs (v3.0), with production and sandbox environments
- `OrcidOAuth` connector for the OAuth endpoints: `authorizationUrl()`, `exchangeCode()`, `clientCredentials()`
- `OrcidToken` and `Scope`
- `OrcidOAuth::refresh()`, `revoke()` and `userInfo()` (`UserInfo`)
- Typed exceptions (`OrcidException` and subclasses) with `isRetryable()`; `sendOrFail()` on both connectors
- `$orcid->record($id)->affiliations()`: list, get, create, update and delete the 7 affiliation types
  (`Affiliation`, `AffiliationInput`, `AffiliationType`, `FuzzyDate`, `ExternalId`, `Organization`)
- `$orcid->record($id)->person()` (`Person`) and `->activities()` (`ActivitiesSummary`)
- `PeerReviewRole` and `PeerReviewType` enums
- `OrcidId` normalisation and checksum validation
- `RequiresMemberApi` / `RequiresScopes` request contracts, checked before sending; optional token `scopes` on `Orcid::member()`
