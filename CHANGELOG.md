# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
