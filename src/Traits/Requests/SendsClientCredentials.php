<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Traits\Requests;

use Saloon\Http\PendingRequest;
use Saloon\Repositories\Body\FormBodyRepository;
use VincentAuger\OrcidSdk\OrcidOAuth;

/**
 * Adds the OAuth client id and secret to the form body of token endpoint requests.
 *
 * The credentials are added per request (rather than as a connector body)
 * so they are never sent to endpoints that do not need them, like userinfo.
 */
trait SendsClientCredentials
{
    public function boot(PendingRequest $pendingRequest): void
    {
        $connector = $pendingRequest->getConnector();

        if (! $connector instanceof OrcidOAuth) {
            throw new \RuntimeException(sprintf('%s must be sent through an OrcidOAuth connector', static::class));
        }

        $body = $pendingRequest->body();

        if (! $body instanceof FormBodyRepository) {
            throw new \LogicException(sprintf('%s must use a form body', static::class));
        }

        $body->merge($connector->clientCredentialsPayload());
    }
}
