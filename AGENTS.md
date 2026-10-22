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

- `Orcid` is the record API connector (`Orcid::member($token)` / `Orcid::public($token)`)
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
- Items created by tests must start their department name with `OrcidFixture::TEST_MARKER` (`orcid-php-sdk`):
  list and activities fixtures only keep those items, so the record's real items never end up in fixtures.
- Hand-written `MockResponse::make()` is only for responses the sandbox can't produce on demand (a fresh
  authorization code, an `openid` token); say so in a comment.
- Without a `.env` file, missing fixtures throw instead of calling ORCID, so CI never reaches the API.
- With a `.env` file, tests refuse to run unless `ORCID_ENVIRONMENT=sandbox`.
- Always review a newly recorded fixture before committing it.

- Tests may only update or delete items they created in the same run. The sandbox client is shared with OSP's
  sandbox, and ORCID treats every item created by that client as ours.

## ORCID API behaviour (verified against the sandbox, 2026-10-22)

Evidence: `tests/Fixtures/Saloon/a1/`.

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
- **403 is ambiguous; use `error-code`:**
  - 9010: the client is not the source of the item (PUT and DELETE, `e7-*`). The token is fine.
  - 9038: the token lacks the required scope (`e9`, a `/read-public` token trying to POST). Its
    `developer-message` says "409 Conflict" even though the status is 403.
