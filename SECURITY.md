# Security

## Reporting Issues

If you find a security issue in this SDK, please open a GitHub issue. If the issue could expose ORCID tokens or
personal data, please contact the maintainer privately first.

## Credential Safety

ORCID access tokens are long-lived (about 20 years) and grant access to a researcher's record:

- **Never commit credentials or tokens to version control**
- Store client credentials in `.env` files (already gitignored) or environment variables
- Store user tokens encrypted at rest
- Revoke tokens that are no longer needed (`OrcidOAuth::revoke()`)
- Review recorded test fixtures before committing them

## Notes

- This SDK only communicates with ORCID's official endpoints over HTTPS
- Security updates are released as needed and documented in [CHANGELOG.md](CHANGELOG.md)
