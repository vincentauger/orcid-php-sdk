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

// Member API, with a token granted by the record owner (3-legged OAuth)
$orcid = Orcid::member($accessToken, Environment::Sandbox);

// Public API, ideally with a /read-public client credentials token
$orcid = Orcid::public($readPublicToken);

// OAuth endpoints (orcid.org)
$oauth = new OrcidOAuth($clientId, $clientSecret, Environment::Sandbox);
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
