# ORCID PHP SDK Instructions

## Project Overview

A modern, framework-agnostic PHP SDK for the ORCID API v3.0 (Public and Member APIs, plus the OAuth token
endpoints), built on Saloon. See `PLAN.md` for the roadmap and the decisions behind it.

## Core Rules

- Use PHP 8.4+ syntax only
- Add `declare(strict_types=1);` to every PHP file
- Make all classes final unless inheritance is required
- Use readonly properties wherever possible
- Use constructor property promotion
- Use typed properties and return types
- Follow PSR-12 coding standards
- Never use `var_dump()` or `dd()` in production code
- Never depend on a framework (no Laravel/Symfony packages, no `spatie/laravel-data`)

## Namespaces

- Base: `VincentAuger\OrcidSdk`
- Data: `VincentAuger\OrcidSdk\Data`
- Requests: `VincentAuger\OrcidSdk\Requests`
- Enums: `VincentAuger\OrcidSdk\Enums`

## Architecture

- `Orcid` is the record API connector (`Orcid::member(accessToken:, orcid:, scopes:)` / `Orcid::public($token)`).
  A member connector with an `orcid` is bound to that record: the sections hang off it (`$orcid->affiliations()`).
  `record($id)` only reads, on every connector (ADR 0003)
- `OrcidOAuth` is the OAuth connector (orcid.org): token exchange, client credentials, refresh, revoke, userinfo
- `Enums\Environment` resolves every host for production and sandbox; never hard-code hosts elsewhere
- `Orcid`'s base URL is the host only: record requests start their endpoint with `/v3.0` (`ApiVersion::V3_0`);
  webhook endpoints (premium members) are not versioned
- The SDK is stateless: it never stores, refreshes or looks up tokens on its own
- Saloon retries stay disabled; callers (e.g. queues) decide when to retry
- DTOs are `final readonly` with hand-written `fromArray()`/`toArray()` mapping ORCID's hyphenated keys

## ORCID credentials

- Public API client: self-service (Developer Tools), `/read-public` and `/authenticate` only
- Member API client: issued by ORCID to members; 3-legged write scopes, and `/read-public` too
- Premium member: membership tier adding webhooks (`/webhook`) and multiple client-credentials integrations

Tests use a sandbox member client. Requests needing premium features must say so in their docblock.

## Testing & Quality Tools

Run these commands before committing:

- `composer test` - lint, unit tests (without the `live` group), types, refactor
- `composer test:live` - tests that hit the ORCID sandbox (needs `.env`)
- `composer lint` - Laravel Pint code formatting
- `composer refactor` - Rector code improvements

### Fixtures

- Follow the DataCite SDK pattern: each typed request is tested with
  `new MockClient([SomeRequest::class => new OrcidFixture('folder/name')])`. With a `.env`, a missing fixture is
  recorded from the sandbox on the first run; afterwards it is replayed.
- Use `OrcidFixture`, never plain `MockResponse::fixture()`: it is the same Saloon fixture, plus replacement of
  ORCID iDs (→ `0000-0002-1825-0097`), client ids (→ `APP-0000000000000000`), tokens and cookies. Profile
  content is kept: the sandbox record only holds test content. Never record against a record with real
  personal data.
- Requests that depend on each other's results (e.g. get a token, then revoke it) must be re-recorded together:
  delete all of their fixtures first, or a replayed (redacted) value ends up in a live call.
- Items created by tests must start a marker field with `OrcidFixture::TEST_MARKER` (`orcid-php-sdk`): the
  department name for affiliations, the review identifier's value for peer reviews, the title otherwise (the
  proposal's for research resources). List and activities fixtures only keep those items (a group holding one is
  kept whole), so the record's real items never end up in fixtures, apart from the hand-made test content of issue #3:
  the hand-added work grouped with a test work on purpose (`HAND_ADDED_DOI` in `tests/Pest.php`), and the other
  names and websites the researcher added (`OrcidFixture::HAND_ADDED_ITEMS`). A new section adds its summary key and
  marker field to `OrcidFixture::TEST_MARKER_FIELDS`; a person section, whose items are filtered one by one (in its
  list and in the person section), its item key to `OrcidFixture::TEST_MARKER_ITEMS`. Addresses are the exception:
  an address has only a country, nothing to mark, so they are not filtered and tests use countries the researcher
  doesn't list (`AQ`, `BV`).
- Each test item has its own `self` external identifier (`testAffiliation()` in `tests/Pest.php`), so ORCID never
  groups test items together. Only the works list tests share one, `HAND_ADDED_DOI`, to join the hand-added work.
- While recording, responses are live and carry real ids; once replayed, the placeholders. Assertions that run
  before a chain's cleanup must hold for both (e.g. `$this->isOurs($source)`), or a failed recording leaves items
  behind on the sandbox.
- Every test that covers a call sends it through the typed requests (no generic test request). Errors are
  recorded too (`errors/`). Hand-written `MockResponse::make()` is only for responses the sandbox can't produce
  on demand (a fresh authorization code, an `openid` token, rate limits, 5xx, connection failures); say so in a
  comment.
- Without a `.env` file, missing fixtures throw instead of calling ORCID, so CI never reaches the API.
- With a `.env` file, tests refuse to run unless `ORCID_ENVIRONMENT=sandbox`.
- Always review a newly recorded fixture before committing it.

- Tests may only update or delete items they created in the same run. The sandbox client is shared with OSP's
  sandbox, and ORCID treats every item created by that client as ours.

## ORCID API behaviour (verified against the sandbox, 2026-10-22)

Evidence: `tests/Fixtures/Saloon/a1/`. No test replays these: they record payloads the SDK never sends.

- **PUT replaces the whole item.** A field sent as `null` and a field left out both clear it (`e3b`, `e3d`),
  so `toArray()` leaves out null fields.
- **`null` means "left out" everywhere**: POST with explicit nulls works (`e10`, OSP's current payload), and
  required fields sent as `null` fail exactly like missing ones (`e12`, `e14`).
- **Empty strings are rejected**: `""` on any text field is a 400 schema error, 9047 (`e11`, `e13`). Text must
  contain at least one non-whitespace character.
- **Dates are optional**, and partial dates (year only, or month/day `null`) are fine (`e16`, `e17`, `e18`).
- **Dates:** ORCID returns `{"year": {"value": "2021"}, ...}`. It also accepts plain strings
  (`{"year": "2021"}`, `e2`), but the SDK always sends the documented `{value}` form.
- **Organization:** required (400, error 9046, `e15`). Its `disambiguated-organization` is required (400, error 9045, `e4`). City and country are
  required for education and employment (400, error 9060, `e5`); other affiliation types are not checked yet.
- **POST** returns 201 with an empty body; the put-code is the last segment of `Location` (`e1-post-*`).
- **PUT** returns 200 with the item; on an unknown put-code, 404 with error 9016 (`e6`).
- **DELETE** returns 204 (`cleanup-delete`); a GET afterwards returns 404 (`get-deleted`).
- **Errors have two body shapes:**
  - ORCID errors: `{"response-code", "developer-message", "user-message", "error-code", "more-info"}`
  - OAuth errors (bad or revoked token): `{"error": "invalid_token", "error_description"}`, 401 (`e8`)
- **OAuth** (`tests/Fixtures/Saloon/oauth/`): client credentials tokens have **no refresh token**
  (`client-credentials`); revoke returns 200 with an empty body (`revoke`); `/oauth/userinfo` needs an `openid`
  token, otherwise 403 `access_denied` with the description under `error-description` (hyphen,
  `userinfo-without-openid`).
- **Visibility:** the Member API with `/read-limited` returns `limited` items (e.g. the email in
  `record/person-member`); the Public API, even without a token, only returns public ones (`record/person-public`).
- **All 7 affiliation types** accept the same body and endpoints (`affiliations/<type>/`).
- **Visibility can't be set by a client** (verified 2026-11-02, `e19`, `e20`): sent on a POST, it is ignored and
  the researcher's default applies (`private` sent, `public` stored); sent on a PUT with another value than the
  current one, 403 with error 9035 ("You can't change the visibility of an item"). So input classes have no
  visibility.
- **403 is ambiguous; use `error-code`:**
  - 9010: the client is not the source of the item (PUT and DELETE, `e7-*`). The token is fine.
  - 9035: a PUT tried to change the item's visibility (`e20`).
  - 9038: the token lacks the required scope (`e9`, a `/read-public` token trying to POST). Its
    `developer-message` says "409 Conflict" even though the status is 403.

## Works (verified against the sandbox, 2026-11-02)

Evidence: `tests/Fixtures/Saloon/c1/` (no test replays these either). Everything under "ORCID API behaviour"
above holds for works too.

- **Required:** a title (9047, `p04`), a type (9047, `p05`) and an external id with the relationship `self`
  (400, error 9023): none at all (`p02`), only `part-of` (`p03`) or only `version-of` (`p27`) all fail. An
  external id type ORCID doesn't know is 9023 too (`p10`). Works have no start or end date, only a
  publication date.
- **Duplicates:** a POST whose `self` id this client already used is 409, error 9021 (`p08`), and so is a PUT
  that gives a work another one's `self` id (`p15`). Ids are compared normalized: a DOI differing only in case
  is a duplicate (`p19`). The developer message names the existing put-code
  (`OrcidConflictException::existingPutCode()`).
- **Enums:** an unknown type, citation type, language code or country is 400, 9047 (`p06`, `p13`, `p12`,
  `p25`); an unknown contributor role is 400, error 9058 (`p11`). Roles: ORCID's original ones and the CRediT
  URIs, stored as sent (`p09`). `undefined` is accepted as a type (`q01`) and some aliases are mapped
  (`dataset` → `data-set`, `q05`); the SDK sends neither.
- **Strings:** a blank title or journal title is 9047 (`p07`, `q09`); a title over 1000 characters too
  (`p21`); a blank citation value is 400, error 9020 (`p22`); a translated title needs a language code (9047,
  `p14`). A blank subtitle or URL is dropped (`q02`, `q08`), a blank short description stored as `""` (`q03`).
- **Not checked by ORCID:** the URL (`q07`, "not a url" stored) and the external id's format (`q04`: an invalid
  DOI is stored, with `external-id-normalized-error` 8001). A contributor needs no name or iD (`p24`).
  `contributor-email` is accepted but never returned (`q06`), so the SDK doesn't send it.
- **PUT** needs the put-code in the body as well: missing or different, 400, error 9019 (`q11`, `q12`); an
  unknown put-code is 404, 9016 (`q10`). A contributor iD sent as `{"path"}` alone is enough: ORCID adds the
  `uri` and `host` (`works/lifecycle/get`).
- **Groups** (verified 2026-11-03): a test work whose DOI is `https://doi.org/10.1016/J.POCEAN.2016.07.005` joins
  the hand-added work's group (`10.1016/j.pocean.2016.07.005`): both normalize to the lowercase bare DOI.
- **Preferred summary = highest display index**, listed first; the group's external ids are its `self` ones, as
  sent (`part-of` ids are left out). A work added by a client gets `"1"`, one added by the researcher `"0"`, so
  the client's work is preferred (`works/list/list`). Choosing a preferred source on the website gives that
  summary the highest index (`"2"`), and it stays preferred when a client adds another work to the group
  (`works/preferred/list`, `WorksLiveTest`). The SDK breaks a tie with the first summary listed (never seen).
- **Sandbox state:** the hand-added work is the researcher's preferred source in its group since 2026-11-03.
- **Bulk** (verified 2026-11-03, `c1/b*`): `POST /works` (`{"bulk": [{"work": ...}]}`) and `GET /works/{a,b,...}`
  answer 200 with `{"bulk": [...]}`, one `{"work"}` or `{"error"}` per item in the order sent; the error has the
  ORCID error body. A created work comes back in full, with its put-code. Each work is checked on its own: a
  duplicate of a work already on the record or earlier in the same batch is 409, 9021 (`b04`, `b01`), no `self`
  id is 400, 9023 (`b02`), and the others are created. A body ORCID can't deserialize (e.g. an unknown type) fails
  the whole request, 400, 9047, and creates nothing (`b03`). An unknown put-code on a bulk read is an item error
  400, 9034, not a 404 (`b06`). Above 100 items: 400, error 9042 on a read (`b07`), 9006 on a create (`b08`).

## Fundings (verified against the sandbox, 2026-11-03)

Evidence: `tests/Fixtures/Saloon/c2/` (no test replays these either). Everything under "ORCID API behaviour"
above holds for fundings too.

- **Required:** a title (400, error 9022, `f05`), a type (9047, `f06`), the organization (9047, `f08`) with a
  name (9047, `g11`), an `address` (9047, `g10`) and a `disambiguated-organization` (9045, `f09`), and an
  external id (9023, `f02`). Unlike works, any relationship will do: `part-of` (`f03`) and `funded-by` (`f04`)
  alone are accepted. An unknown external id type is 9023 too (`g16`).
- **Address:** city and country are optional, unlike education and employment: city only (`f11`), country
  only (`f10`) and even `{}` (`g20`) are accepted. The SDK requires a city or a country, so it never sends `[]`.
- **Dates:** an end date without a start date is 400, error 9050 (`f12`); a start date alone is fine (`f14`).
  An end date before the start date is accepted (`f13`); the SDK refuses it, as for affiliations.
- **Not in the funding schema** (9047): a title's `subtitle` (`f19`), a contributor's `contributor-sequence`
  (`f18`). `contributor-email` is accepted but never returned (`f15`), so the SDK doesn't send it. A contributor
  sent with an iD comes back with the record's credit name, not the one sent (`f15`).
- **Roles:** `lead`, `co-lead`, `supported-by`, `other-contribution` and the CRediT URIs (`f15`, `f17`); a work
  role is 400, error 9058, whose message lists them all (`f16`).
- **Enums and lengths** (9047): an unknown type (`f07`) or currency (`f21`, and a lowercase one, `f34`), a
  translated title's unknown language (`f30`), a blank title (`g12`) or credit name (`g19`), an address with an
  unknown country (`g18`); a title over 1000 characters (`f26`), an organization-defined type over 255 (`f29`),
  a short description over 5000 (`g17`).
- **Amount:** both the value and the currency are required: a currency alone is 9020 (`f24`), a value alone 9047
  (`f20`). The value is parsed as a decimal number: `1000`, `1e5`, `0`, `.5`, `-5`, 20 digits with decimals, 300 digits
  are accepted (`g01`, `g02`, `g05`, `g07`, `f33`, `g08`, `g21`); `1,000,000`, `1 000`, `1000,50`, `1.000,50` and `a lot` are 400, error 9054
  (`f23`, `g03`, `g04`, `g06`, `f22`). It is stored normalized: `125000.50` is read back as `125000.5` (`f15`).
- **Blank strings:** a blank organization-defined type or URL is dropped, a blank short description stored as
  sent (`g13`).
- **Duplicates:** a POST whose `self` id this client already used is 409, error 9021, naming the existing
  put-code (`f25`), and so is a PUT taking another funding's `self` id (`f37`). Two fundings sharing only a
  `part-of` id are both created (`g14`, `g15`).
- **PUT** needs the put-code in the body (400, error 9019, `f35`); an unknown put-code is 404, 9016 (`f36`).
- **Lists** (`f38`): `{"group": [{"external-ids", "funding-summary": [...]}]}`, like works; a funding without a
  `self` id is a group of its own with no external ids. A summary has no amount, contributors, description or
  organization-defined type. A client's funding gets display index `"1"`.

## Peer reviews (verified against the sandbox, 2026-11-03)

Evidence: `tests/Fixtures/Saloon/c3/` (no test replays these either). Everything under "ORCID API behaviour"
above holds for peer reviews too.

- **Required** (all 400, 9047 schema errors): the reviewer role (`r02`), review identifiers (`r03`), the review type
  (`r05`), the completion date (`r06`), the review group id (`r07`), the convening organization (`r08`) and its
  `address` (`r11`). Like fundings, any relationship will do for the review identifier (`part-of` only, `r04`), and
  a year alone is a valid date (`r27`).
- **Convening organization:** unlike fundings, the `disambiguated-organization` is optional (`r09`), and a country
  without a city is accepted (`r10`).
- **Review group id:** must match `(ringgold|issn|orcid-generated|fundref|publons):` plus 2 characters or more, else
  9047 (`r14`, a bare ISSN `r30`). An `issn:` group is created on first use (`r15`, then read from the registry,
  `r15-registry`); ORCID only checks the ISSN's form and check digit: a wrong check digit (`r12`) or a placeholder
  like `1488-XXXE` (`r37`) is 400, error 9053, but `0000-0019`, which no journal has, is accepted (`r28`). A
  lowercase `x` is stored uppercase (`r29`). Any other group must exist: 400, error 9020 (`r13`).
- **Group-id registry** (`/v3.0/group-id-record`, a `/group-id-record/read` client credentials token): reading an
  unregistered ISSN creates its group too, and the sandbox's id sequence can collide (9008, seen once on
  `1488-5379`, which a POST later created fine, `r38`).
- **Enums:** an unknown role, review type or subject type is 400, 9047 (`r19`, `r20`, `r17`). The subject type
  takes every work and funding type and `research-resource-proposal` (`grant`, `r18`).
- **Subject:** its external identifier needs a relationship (400, 9023, `r31`), and an unknown identifier type is
  9023 too (`r26`). A subject-name `subtitle` is dropped without an error (`r16-get`). A blank container name or
  subject title is 9047 (`r22`, `r33`), and so is a container name over 1000 characters (`r32`).
- **Duplicates:** a POST whose `self` review identifier this client already used is 409, error 9021, naming the
  existing put-code (`r21`). A review identifier equal to another review's subject DOI is fine (`r34`).
- **PUT** needs the put-code in the body (400, error 9019, `r23`); an unknown put-code is 404, 9016 (`r24`).
- **Lists** (`r35`): `{"group": [{"external-ids": <the review group id, type "peer-review">, "peer-review-group":
  [{"external-ids", "peer-review-summary": [...]}]}]}`: grouped by review group, then by `self` review identifier
  (the subject groups nothing: `r16` and `r25` share a subject DOI but not a group). A peer review without a `self`
  id is a group of its own with no external ids. A summary has nothing about the subject, names its review
  identifiers `external-ids` and its date `completion-date`. A client's peer review gets display index `"1"`.
- **Test marker:** a summary has no subject name, so test peer reviews start their review identifier's value with
  `OrcidFixture::TEST_MARKER` (`testPeerReview()`), in the review group `TEST_REVIEW_GROUP` (a DFO series).

## Research resources (verified against the sandbox, 2026-11-03)

Evidence: `tests/Fixtures/Saloon/c4/` (no test replays these either). Everything under "ORCID API behaviour"
above holds for research resources too.

- **Required** (all 400, 9047 schema errors): the proposal (`s02`), its title (`s03`), hosts (`s04`, an empty list
  `s05`) and external ids (`s06`); at least one resource item (`s08`, an empty list `s32`), each with a name (`s09`),
  a type (`s10`), hosts (`s12`, `s36`) and external ids (`s13`). Any relationship will do, on the proposal (`part-of`
  only, `s07`) and on an item (`s23`). Proposal ids of type `grant_number` and item ids of type `doi` work too
  (`s37`); an unknown id type is 400, 9023, on either (`s22`, `s21`).
- **Hosts** (proposal and items alike): a non-blank name is assumed required, as for fundings (`c2/g11`, not
  probed here); an `address` is required (9047, `s15`, `s35`), a country alone is enough
  (`s16`), and so is a `disambiguated-organization` (400, error 9045, `s14`, `s34`).
- **Dates:** optional; an end date without a start date is 400, error 9050 (`s17`); an end date before the start date
  is accepted (`s18`), the SDK refuses it, as for fundings.
- **Enums and strings** (9047): an unknown resource type (`s11`; ORCID's are `infrastructures`, `collections`,
  `equipment`, `services`, `s23`), a title `subtitle` (`s19`), a translated title without a language code (`s33`, read as an unknown one), a blank
  resource name (`s24`), a title or resource name over 1000 characters (`s25`, `s26`). A blank proposal or item URL is
  dropped (`s27`). An RRID ORCID can't parse is stored with `external-id-normalized-error` 8001 (`s23-get`).
- **Duplicates:** a POST whose `self` proposal id this client already used is 409, error 9021, naming the existing
  put-code (`s20`). Two research resources sharing only a `part-of` id are both created (`s30`, `s31`).
- **PUT** needs the put-code in the body (400, error 9019, `s28`); an unknown put-code is 404, 9016 (`s29`); a PUT
  replaces the items too (`s39`).
- **Lists** (`s38`): `{"group": [{"external-ids", "research-resource-summary": [...]}]}`, like fundings, grouped by the
  proposal's `self` ids; a research resource without one is a group of its own with no external ids. A summary holds
  the proposal only, no resource items. A client's research resource gets display index `"1"`, on a GET of the full item too
  (`s01-get`), but a PUT's response has none (`s39`).
- **Test marker:** test research resources start their proposal title with `OrcidFixture::TEST_MARKER`
  (`testResearchResource()`), and have their own `self` proposal id.

## Keywords (verified against the sandbox, 2026-11-03)

Evidence: `tests/Fixtures/Saloon/c5/` (no test replays these either). Everything under "ORCID API behaviour"
above holds for keywords too. Writes need `/person/update`.

- **Body:** `{"content": "..."}` (a plain string: `{"value"}` is 400, 9047, `k12`). A missing or `null` content is 9047
  (`k23`, `k04`), a blank one 400, error 9020 ("Keyword cannot be null", `k03`, on a PUT too `k18`). At most 255
  characters (`k24`; 256 is 9047, `k07`).
- **Stored as sent:** not trimmed (`k25-get`), so `"x "` is not a duplicate of `"x"` (`k28`).
- **Duplicates:** a POST whose content this client already used is 409, error **9030** (not 9021), and so is a PUT
  taking another keyword's content (`k08`, `k27`). The message names the content, not the put-code. Compared
  case-sensitively: the uppercase content is a new keyword (`k09`).
- **Ignored on a POST:** `visibility` (`private` sent, `public` stored, `k10`) and `display-index` (`5` sent, `1`
  stored, `k11`). A PUT changing the visibility is 403, 9035 (`k17`).
- **PUT** needs the put-code in the body (400, error 9019, `k14`, a different one `k15`) and returns the item
  (`k13`); an unknown put-code is 404, 9016 (`k16`, a GET `k20`). DELETE returns 204; afterwards GET and DELETE are
  404, 9016 (`k21`, `k22`).
- **Lists** (`k19`): `{"last-modified-date", "keyword": [...], "path"}`, not grouped; the person section holds the same
  list under `keywords`. A client's keyword gets display index `"1"`.
- **Test marker:** test keywords start their content with `OrcidFixture::TEST_MARKER` (case-sensitive): the
  researcher's own keywords never end up in fixtures, even in the person section's.

## Other names, researcher URLs, external identifiers, addresses (verified against the sandbox, 2026-11-03)

Evidence: `tests/Fixtures/Saloon/c6/` (no test replays these either). Everything under "ORCID API behaviour"
above holds for these too, and so does what keywords showed for PUT (put-code in the body, 9019: `o13`, `u18`,
`x19`, `a12`; unknown put-code 404, 9016: `o14`, `u20`, `x20`, `a13`). Writes need `/person/update`.

- **Other names** (`/other-names`, `o*`): `{"content": "..."}`, a plain string (`{"value"}` is 9047, `o09`); missing
  or `null` is 9047 (`o04`; `o02` sent `[]`, not an object), at most 255 characters (`o05`; 256 is 9047, `o06`). **A blank name is accepted**
  and stored as sent, on a POST and a PUT (`o03`, `o15`), and so is padding (`o11-get`): `OtherNameInput` trims and
  refuses blank. Duplicates: 409, 9030, case-sensitive (`o07`, `o08`); the researcher's own name added by this
  client is fine (`o10`).
- **Researcher URLs** (`/researcher-urls`, `u*`): `{"url-name", "url": {"value"}}`. The URL is required (9047,
  `u03`), a blank one 400, error 9020 (`u04`), at most 2000 characters (9020, `u13`, `u14`); it is not checked:
  `not a url`, no scheme, `ftp://` are stored (`u05`, `u06`, `u15`). The name is optional (`u01`), a blank one 9047
  (`u07`), at most 350 characters (`string-350`, `u08`). Duplicates: 409, 9030 on the URL, case-sensitive (`u10`,
  `u16`); a name may repeat (`u11`). A PUT without the name clears it (`u19`).
- **External identifiers** (`/external-identifiers`, `x*`): type, value and URL are required (9047 for a missing
  type or value, `x02`, `x03`; 400, error 9020 for the URL, `x05`); the relationship must be `self` (9020, missing
  `x04` or `part-of` `x07`). The type is free text (`x06`, `x16`), type and value at most 255 characters (9020,
  `x17`; `x22` accepted, `x23` refused: ORCID calls the value "Reference field", as for the missing URL in `x05`),
  blank ones 9047 (`x10`, `x11`); the URL is not checked (`x14`) and 2000 characters are accepted (`x24`, `x25`). Duplicates: 409, 9030 on the type and
  value, whatever the URL (`x08`, `x15`; the message names the URL); the same value with another type is fine (`x09`). The researcher can't add
  one on the sandbox website (issue #3).
- **Addresses** (`/address`, singular; `/addresses` is 404, 9001, `a15`; `a*`): `{"country": {"value"}}`, required
  (`a03`; `a02` sent `[]`, not an object), an uppercase ISO 3166-1 alpha-2 code: unknown, lowercase, alpha-3, blank and plain-string values
  are 400, 9047 (`a04`, `a05`, `a09`, `a10`, `a06`). **No duplicate check**: the same country twice, or the
  researcher's own, is accepted (`a07`, `a08`).
- **Lists** (`o16`, `u21`, `x21`, `a14`): `{"last-modified-date", "<item>": [...], "path"}`, not grouped, like
  keywords. A client's item gets display index `"1"`.
- **Test marker:** other names by their content, researcher URLs by their name (`url-name`), external identifiers by
  their value (`OrcidFixture::TEST_MARKER_ITEMS`). Items the researcher added by hand on the website (other names and
  URLs, issue #3) are kept in fixtures too (`OrcidFixture::HAND_ADDED_ITEMS`), so the person fixtures replay them;
  keywords stay strict. Addresses are not filtered: an address has nothing to mark, so test addresses use countries
  the researcher doesn't list (`AQ`, `BV`).

## Agent skills

### Issue tracker

Issues live in GitHub Issues on `vincentauger/orcid-php-sdk`, through the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

The five default labels: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.
