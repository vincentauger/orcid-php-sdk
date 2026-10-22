# CONTRIBUTING

Contributions are welcome, and are accepted via pull requests.
Please review these guidelines before submitting any pull requests.

## Process

1. Fork the project
1. Create a new branch
1. Code, test, commit and push
1. Open a pull request detailing your changes

## Guidelines

* Please ensure the coding style running `composer lint`.
* Send a coherent commit history, making sure each individual commit in your pull request is meaningful.
* You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
* Please remember that we follow [SemVer](http://semver.org/).

## Development Setup

```bash
git clone https://github.com/vincentauger/orcid-php-sdk.git
cd orcid-php-sdk
composer install
composer test
```

By default, tests only replay the recorded fixtures in `tests/Fixtures/Saloon` and never call ORCID.

## Recording fixtures against the ORCID sandbox

You need a [sandbox](https://sandbox.orcid.org) ORCID record and sandbox **member** API credentials.

ORCID has three kinds of credentials, and they are not interchangeable:

- **Public API client**: anyone can create one from the Developer Tools page of their ORCID account. It can
  only read public data (`/read-public`) and authenticate users. It is not enough to run these tests.
- **Member API client**: issued by ORCID to member organisations (sandbox credentials included). It can read
  limited data and write to records with the owner's permission, and can also get a `/read-public` token.
- **Premium member**: a membership tier on top of the member API, which adds webhooks and multiple
  client-credentials integrations. Only needed for the webhook tests.

```bash
cp .env.example .env
```

Fill in `ORCID_CLIENT_ID`, `ORCID_CLIENT_SECRET` and `ORCID_REDIRECT_URI` (a redirect URI registered on your
sandbox client, e.g. `https://localhost/callback`; it doesn't need to work). Tests refuse to run unless
`ORCID_ENVIRONMENT=sandbox`.

### Getting a user access token

Writing to a record needs a 3-legged token granted by that record's owner. Sandbox tokens last about 20 years,
so this is a one-off:

```bash
bin/sandbox-token.sh
```

The script prints an authorization URL. Open it while signed in to the sandbox as your test record, authorize,
then paste back the URL you were redirected to. It writes `ORCID_ACCESS_TOKEN` and `ORCID_ORCID_ID` to `.env`.
It needs `curl` and `jq`.

### Recording

Delete the fixture you want to refresh and run the test: with a `.env` file, a missing fixture is recorded from
the sandbox through `Tests\Fixtures\OrcidFixture`, which removes ORCID iDs, tokens and personal data.
**Review every new fixture before committing it.**

Tests may only update or delete items they created in the same run. Your sandbox client may be shared with an
application's sandbox (it is for OSP), and ORCID lets a client change any item it created.

Tests that must talk to the sandbox every time belong to the `live` group (`composer test:live`) and call
`$this->requiresSandbox()`.
