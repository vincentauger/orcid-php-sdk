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
  department name for affiliations. List and activities fixtures only keep those items (a group holding one is
  kept whole), so the record's real items never end up in fixtures, apart from the hand-added work grouped with a
  test work on purpose (`HAND_ADDED_DOI` in `tests/Pest.php`). A new section adds its summary key and marker field to `OrcidFixture::TEST_MARKER_FIELDS`.
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

## Agent skills

### Issue tracker

Issues live in GitHub Issues on `vincentauger/orcid-php-sdk`, through the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

The five default labels: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.
