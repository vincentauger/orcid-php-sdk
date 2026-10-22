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

### Affiliations

Employments, educations, qualifications, invited positions, distinctions, memberships and services all work
the same way:

```php
use VincentAuger\OrcidSdk\Data\Affiliations\AffiliationInput;
use VincentAuger\OrcidSdk\Data\Common\FuzzyDate;
use VincentAuger\OrcidSdk\Data\Organization\DisambiguatedOrganization;
use VincentAuger\OrcidSdk\Data\Organization\Organization;
use VincentAuger\OrcidSdk\Data\Organization\OrganizationAddress;
use VincentAuger\OrcidSdk\Enums\AffiliationType;

$affiliations = Orcid::member($accessToken)->record('0000-0002-1825-0097')->affiliations();

$input = new AffiliationInput(
    organization: new Organization(
        'Fisheries and Oceans Canada',
        new OrganizationAddress(city: 'Ottawa', country: 'CA'),
        DisambiguatedOrganization::ror('https://ror.org/02qa1x782'),
    ),
    roleTitle: 'Research Scientist',
    startDate: FuzzyDate::fromString('2021-01'),
);

$putCode = $affiliations->create(AffiliationType::Employment, $input);   // store it
$affiliations->update(AffiliationType::Employment, $putCode, $input);     // replaces the whole item
$affiliations->delete(AffiliationType::Employment, $putCode);
$employments = $affiliations->list(AffiliationType::Employment);
```

### Person and activities

```php
$record = Orcid::public()->record('https://orcid.org/0000-0002-1825-0097'); // bare iD or URL

$person = $record->person();
$person->name?->displayName();
$person->keywords;

$activities = $record->activities();
$activities->affiliations(AffiliationType::Education);
$activities->workCount;
```

### Errors

Every convenience method throws a `VincentAuger\OrcidSdk\Exceptions\OrcidException`. Use `isRetryable()` to
decide whether to try again later (rate limits, ORCID outages, connection failures):

```php
try {
    $affiliations->update(AffiliationType::Employment, $putCode, $input);
} catch (OrcidAuthException $e) {
    // 401: the token was revoked or expired; the user must authorize again
} catch (OrcidForbiddenException $e) {
    // 403: $e->isNotSource() (another client created the item) or $e->isMissingScope()
} catch (OrcidException $e) {
    if ($e->isRetryable()) {
        // retry later
    }
}
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
