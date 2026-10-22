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

## Follow-ups (not in this work)

Writing works and peer reviews (later `0.x`) · an implicit/OpenID flow authorization URL (`response_type=token`,
`openid` scope) for apps that use it; `userInfo()` already covers the server side · OSP can use its premium membership's multiple client-credentials
integrations to give dev, staging and prod separate clients · real organization cities · notifying authors whose ORCID
connection stopped working · a UI prompt to reconnect when the token's
scope is too narrow.
