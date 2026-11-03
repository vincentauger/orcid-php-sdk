# Plan: ORCID SDK extraction

Agreed 2026-10-22. Extract OSP's ORCID Saloon integration (`app/Http/Integrations/Orcid`) into this
framework-agnostic package, modelled on `vincentauger/datacite-php-sdk`.

## A. SDK (`vincentauger/orcid-php-sdk`)

- [x] **A0. Repo setup** — `VincentAuger\OrcidSdk`, MIT, PHP `^8.4`, Saloon `^4`, tooling copied from the
  DataCite SDK. Public from day one; `0.x` tags only until OSP has run it in production. ORCID API v3.0 only.
- [x] **A1. Sandbox test** (settles the payload format; results in `AGENTS.md`) — against the sandbox record, check:
  1. PUT with a field set to `null` vs left out, then GET to see what ORCID kept;
  2. dates sent as `{"year": "2021"}` vs `{"year": {"value": "2021"}}`;
  3. whether `disambiguated-organization` and `address.city` are required.

  Record the first fixtures through `Tests\Fixtures\OrcidFixture` (redacts iDs, tokens, personal data).
  Write the results in `AGENTS.md`, citing the fixtures. If OSP's current date format turns out to be
  ignored, note it for OSP's release notes.
- [ ] **A2. Connectors and authentication**
  - [x] `Environment` (Production/Sandbox → OAuth, Member and Public hosts), `ApiAccess`, `ApiVersion`
  - [x] `Orcid` connector (`member()` / `public()`), bearer token, 10 s / 30 s timeouts, no retries.
    The base URL is the host only; record requests add `/v3.0` through a shared trait (`ApiVersion::V3_0`)
    because webhook endpoints are not versioned.
  - [x] `OrcidOAuth`: `authorizationUrl()` (only builds the URL), `exchangeCode()`, `clientCredentials()`;
    client id/secret are added per request (`SendsClientCredentials`), never as a connector body
  - [x] `OrcidOAuth`: `refresh(revokeOld)`, `revoke()`, `userInfo()`
  - [x] `OrcidToken` readonly class: `accessToken`, `refreshToken`, `expiresIn`, `scopes`, `orcid`, `name`
  - [x] `Scope` enum (`encode`/`decode`/`completeAccess`; unknown scopes ignored when decoding)
  - [x] `Contracts\RequiresMemberApi` and `Contracts\RequiresScopes`, checked in `Orcid::boot()`; optional
    `scopes:` on `Orcid::member()` → `MissingScopeException` before anything is sent
- [x] **A3. How calls are organised** — `$orcid->record($id)` (bare iD or ORCID URL, checksum-validated):
  - [x] `->affiliations()->list|get|create|update|delete(AffiliationType, …)`; `create()` returns the put-code
    read from `Location`, all through `sendOrFail()`; `send()` still gives the raw `Response`
  - [x] `->person()` and `->activities()` (return the data directly; no extra `get()`/`summary()` step)
- [x] **A4. Request and response classes** — `final readonly`, hand-written `fromArray()`/`toArray()`.
  - [x] Affiliations: `Affiliation` (read; list summaries flattened) / `AffiliationInput` (write), one
    `AffiliationType` enum for all 7 types (each recorded against the sandbox). `toArray()` leaves out nulls.
    Required (ORCID errors 9045/9046/9060): organization name, city, country and a disambiguated organization;
    blank optional strings become `null`, blank required ones throw `InvalidArgumentException`. Shared
    `FuzzyDate`, `ExternalId`, `Source`, `Organization*` classes.
  - [x] `Person` (name, other names, biography, URLs, emails, countries, keywords, external identifiers) and
    `ActivitiesSummary` (typed affiliations; works, fundings, peer reviews and research resources counted only)
  - [x] Enums: `PeerReviewRole`, `PeerReviewType`
- [x] **A5. Exceptions** — `OrcidException` (RuntimeException) → `OrcidRequestException` (parsed error
  body + `Response`; reads both ORCID error bodies and OAuth `{error, error_description}` bodies) →
  `Auth` (**401 only**: bad, revoked or expired token), `Forbidden` (403, with `errorCode`, `isNotSource()` for 9010
  and `isMissingScope()` for 9038), `NotFound`, `Validation`, `Conflict`, `RateLimit`, `Server`; plus `Connection`
  (wraps `FatalRequestException`). `isRetryable()` is true for 429, 5xx and connection errors. Connectors return
  them from `getRequestException()`, so `$response->throw()` raises them too; convenience methods use
  `sendOrFail()`, and `send()` still returns the raw response.
- [ ] **A6. Tests and docs** — fixture-based tests in CI, `live` Pest group against the sandbox (only runs
  when credentials are present), README with examples.
  - [x] `bin/sandbox-token.sh`: one-off shell script that gets a 3-legged sandbox
    token for the test record and writes it to `.env`
  - [x] Recorded: client credentials, revoke, userinfo without `openid`. Mocked from ORCID's docs:
    `exchangeCode()` (needs a fresh browser code), `refresh()` (to record later, once `ORCID_REFRESH_TOKEN` is in `.env`),
    successful `userInfo()` (needs an `openid` token)
  - [x] Affiliation calls recorded through the typed requests. The `a1/` fixtures stay as evidence: they cover
    payloads the SDK deliberately never sends (nulls, plain dates, blank strings, missing ROR)
- [x] **Release `0.1.0`** on Packagist (tagged 2026-10-22; bug fixes go in patches, breaking changes in minors while `0.x`).
- [ ] **Later 0.x: webhooks** (premium members only, not used by OSP) — `Scope::Webhook` (obtained via
  `clientCredentials()`), `$orcid->record($id)->webhooks()->register($callbackUrl)` / `->unregister()`:
  PUT/DELETE with an empty body on the unversioned `/{orcid}/webhook/{url-encoded callback}`; 201 created,
  204 already registered or deleted. Callbacks are empty POSTs batched every 5 minutes, and only cover public data.
  The live test needs a premium sandbox client.

## B. OSP: one PR, checked on dev and staging before prod

1. Composer `path` repository during development → Packagist `^0.1.0`.
2. `app/Orcid/OrcidClientFactory` (registered once in `AppServiceProvider`, only reader of
   `config('osp.orcid.*')`; `oauth()`, `forAuthor()` with decoded scopes) and
   `app/Orcid/Mappers/EmploymentMapper` (`AuthorEmployment` → `AffiliationInput`, keeps `'Ottawa'` as the city
   for now).
3. Controllers (`FullFlow`, `RevokeToken`, `ImplicitFlow`) call `oauth()` instead of `Http::asForm()`, and the
   three copies of `getBaseUrl()` are removed. After a successful callback, dispatch reconcile jobs for the
   author's employments that are out of sync or trashed with a put-code.
4. `SyncAuthorEmploymentWithOrcid` becomes a reconcile job: no `syncType`; trashed → DELETE/`forceDelete()`,
   has a put-code → PUT (404 → POST), none → POST; `WithoutOverlapping($id)`; does nothing if already in sync.
   An author who isn't connected, or an organization missing `country_code` or `ror_identifier` (both required,
   A1), leaves the record pending. Retryable errors propagate (`$tries = 5`, `$backoff = [60, 300, 900, 3600]`);
   401 (`OrcidAuthException`) or 403 missing scope (9038) → `clearOrcidToken()` + `fail()`; 403 not the source
  (9010) → `fail()` + error log, **without** disconnecting the author (it means a misconfiguration, e.g. a put-code
  created under another environment's client); other 4xx → `fail()`, logging `developerMessage`.
5. `ManuscriptPeerReviewer` casts to the SDK enums (no migration). `hasValidOrcidCredentials()` also requires
   `/activities/update`. A revoked token shows through the existing `orcid_verified = false` UI state.
6. Delete `app/Http/Integrations/Orcid`; `ORCIDAuthScope::translate()` moves to an OSP helper with lang files.
7. Tests use `Saloon::fake()` with minimal `MockResponse`s: the reconcile branches, failure handling, records
   left pending, dispatches after reconnecting, and the mapper. Update `OrcidIntegrationTest` to use `Scope`.

## C. Complete the v3.0 API (agreed 2026-11-02)

Goal: every call in ORCID's [v3.0 guide](https://github.com/ORCID/orcid-model/blob/master/src/main/resources/record_3.0/README.md),
JSON only, then `1.0`. Each section follows the affiliations pattern: a sandbox check first (required fields and
error codes, written in `AGENTS.md` with the fixtures as evidence), then `final readonly` read/write classes,
then a resource on the member connector (`$orcid->works()`, ADR 0003) and reads on `$orcid->record($id)`,
all recorded through `OrcidFixture`. The SDK serves OSP first but
stays generic: it is public. Vocabulary: `GLOSSARY.md`; decisions: `docs/adr/`.

Decided for every section:
- Classes: `X` (read: tolerant, never fails on what ORCID returns), `XInput` (write: validated before sending),
  `XSummary` where list items differ from the full item. Input classes have no visibility (ORCID applies the
  researcher's default and refuses changes: verified, `a1/e19`, `e20`); read classes expose a `Visibility` enum.
- Enums ORCID can extend (work, funding and contributor types, roles, ...): read classes hold `?Enum $x` plus
  `string $rawX`, so an unknown value loses nothing; input classes only take the enum.
- Works, fundings and peer reviews: `list()` returns groups, with `preferred()` (one summary per group) and
  `summaries()` (all of them, for syncing). Affiliations stay flat, plus `displayIndex` (ADR 0001).
- One method call sends one HTTP request: bulk calls throw above 100 items, callers split (ADR 0002).
- Finding our own items: `Source::isClient($clientId)`; the connector never knows the client id.
- A member connector is bound to its record: `Orcid::member(accessToken:, orcid:, scopes:)` exposes the
  sections directly; every connector keeps `record($id)` for reads only (a member connector without `orcid`,
  e.g. client credentials for higher limits, only reads that way) (ADR 0003).
- Test items each get their own `self` external id, so ORCID never groups them together.

Order: C0 first; the other sections in any order, one minor each (breaking changes allowed in `0.x`; OSP only
uses affiliations and OAuth). `1.0` = C8.

- [x] **Test cleanup** (before C0): every call is tested through the typed requests; `RawRequest` and the
  tests replaying `a1/` are gone (the `a1/` fixtures stay as evidence). Errors recorded under `errors/`.
  Left: `PersonDataTest` still uses a hand-built payload, because the sandbox record has no other names,
  external identifiers or credit name; add them on the record, re-record `record/person-*`, then move it.
- [x] **C0. Shared groundwork** (#2, `Amount` with #7)
  - [x] Member connector bound to its record (ADR 0003); `record($id)` only reads
  - [x] `Visibility` enum (`public`, `limited`, `registered-only`, `private`) replacing `?string $visibility`
  - [x] `Source`: add `assertion-origin-*` fields (token delegation, Search & Link wizards)
  - [x] `ExternalId`: add the read-only `external-id-normalized` (works and peer reviews)
  - [x] New shared classes: `Amount` (with fundings, #7). Done with works (#4): `Title` (title, subtitle,
    translated title), `Contributor` (iD, credit name, role, sequence), `Url`
  - [x] A `ReadsPutCodeFromLocation` trait for every create request
  - [x] `OrcidFixture`: generalise the `TEST_MARKER` filter beyond `department-name` (work/funding/resource titles,
    peer review identifiers, since a summary has no subject name; person item content), so each new list fixture only keeps test items
  - Typed resources per section, sharing request traits; no generic "section" class, because the payloads differ
  - [x] `Source::isClient()`; `Affiliation::$displayIndex`; unique `self` ids for test affiliations
  - [x] Sandbox: what ORCID does with `visibility` sent by a client (ignored on POST, 403 9035 on a change by PUT:
    inputs stay without visibility)
  - [x] Sandbox: which summary is preferred in a group: the highest display index, listed first (`works/list`,
    in `AGENTS.md`)
- [x] **C1. Works** (what most integrations write). Done (#4): sandbox check (`c1/`, in `AGENTS.md`), `Work` /
  `WorkInput`, the enums, `get|create|update|delete`, duplicates (409, 9021), grouped `list()` (#5), bulk (#6:
  `getMany()` returns a `BulkRead` too, since an unknown put-code is an item error, not a 404)
  - `Work` / `WorkInput` / `WorkSummary` / `WorkGroup`; enums `WorkType` (v3.0 list: `dissertation-thesis`, `software`,
    `annotation`, `physical-object`, ...), `CitationType`, `ContributorRole`, `ContributorSequence`
  - `$orcid->works()->list|get|create|update|delete`, plus `getMany(putCodes)` (`GET /works/{a,b,...}`) and
    `createMany(inputs)` (`POST /works`), both max 100 per call; `createMany()` returns a `BulkResult`
    (`created`: input index → put-code, `failed`: input index → error, `hasFailures()`)
  - v3.0 requires an external id with relationship `self`: check it in the sandbox, then validate in `WorkInput`
  - Sandbox: required fields, duplicate `self` id (409 → `OrcidConflictException`?), bulk partial failures
- [x] **C2. Fundings** (#7; sandbox check in `c2/`, in `AGENTS.md`: a start date is required with an end date,
  9050; any external id relationship will do) — `Funding` / `FundingInput` / `FundingSummary` / `FundingGroup`, `FundingType` (`grant`, `contract`,
  `award`, `salary-award`), `FundingContributorRole`, `Amount` (ISO 4217 currency); reuses `Organization`
  (disambiguated organization required). Sandbox: start date required when an end date is given.
- [x] **C3. Peer reviews** (#8; sandbox check in `c3/`, in `AGENTS.md`: the disambiguated organization is optional,
  an ISSN group is created on first use, 9020 for an unknown group) — `PeerReview` / `PeerReviewInput` /
  `PeerReviewSummary` / `PeerReviewGroup`, reusing `PeerReviewRole` / `PeerReviewType`, plus `PeerReviewSubjectType`.
  ORCID nests groups in their review group: `PeerReviewGroups` keeps one flat list of groups, each with its review
  group id, and `byReviewGroup()` nests them again.
- [ ] **C4. Research resources** (lowest demand: infrastructure providers) — `ResearchResource` with a proposal
  (title, hosts, external ids, dates, URL) and resource items (name, `ResearchResourceType`, hosts, ids).
- [ ] **C5. Person sections** (`/person/update`, Member API)
  - Writable: other names, keywords, researcher URLs, external identifiers, addresses:
    `$orcid->keywords()->list|get|create|update|delete`, etc.
  - Read only: `$orcid->biography()`, `->emails()`, `->personalDetails()`
  - `Person` exposes typed items (with put-codes and visibility) instead of plain strings (breaking); the same
    item classes (`Keyword`, ...) are returned by `person()` and by `->keywords()->list()`
- [ ] **C6. Whole record and summary**
  - whole record → `Record` (person + activities); record summary → `RecordSummary`, on both connector shapes. The guide calls
    `/summary` both "Member API only" and `/read-public`: check both in the sandbox.
  - `ActivitiesSummary` gets typed work, funding, peer review and research resource summaries; counts stay
    as methods
- [ ] **C7. Search** (on the connector: `$orcid->search(...)`, not under a record)
  - `search($solrQuery, start, rows)` → iDs and `numFound`; `expandedSearch()` (names, institutions);
    `csvSearch()` maybe. Raw Solr query string for now, no query builder.
  - The guide says 1000 results by default and `rows` up to 200: check in the sandbox.
  - Fixtures only query our own test record (by iD or marker keyword): other sandbox users' names must not be
    recorded.
- [ ] **C8. `1.0`** — README section per resource, every call covered by a fixture test and a `live` test,
  OSP has run `0.x` in production, public API frozen.

Not planned: XML, API v2.x, token delegation (needs ORCID to enable it on the client), member-only reporting.

## Follow-ups (not in this work)

The group-id record API (`/v3.0/group-id-record`, scopes `/group-id-record/read|update` through
`clientCredentials()`), for peer review groups other than ISSNs · an implicit/OpenID flow authorization URL (`response_type=token`,
`openid` scope) for apps that use it; `userInfo()` already covers the server side · OSP can use its premium membership's multiple client-credentials
integrations to give dev, staging and prod separate clients · real organization cities · notifying authors whose ORCID
connection stopped working · a UI prompt to reconnect when the token's
scope is too narrow.
