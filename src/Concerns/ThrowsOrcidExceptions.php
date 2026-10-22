<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Concerns;

use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;
use VincentAuger\OrcidSdk\Exceptions\OrcidConnectionException;
use VincentAuger\OrcidSdk\Exceptions\OrcidException;
use VincentAuger\OrcidSdk\Exceptions\OrcidRequestException;

/**
 * Error handling shared by the connectors.
 *
 * `send()` keeps Saloon's behaviour and returns the raw response; `$response->throw()`
 * raises the matching OrcidRequestException. Convenience methods use `sendOrFail()`,
 * which also turns connection failures into OrcidConnectionException.
 */
trait ThrowsOrcidExceptions
{
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        return OrcidRequestException::fromResponse($response, $senderException);
    }

    /**
     * Send a request and throw an OrcidException on any failure
     *
     * @throws OrcidException
     */
    public function sendOrFail(Request $request): Response
    {
        try {
            return $this->send($request)->throw();
        } catch (FatalRequestException $exception) {
            throw new OrcidConnectionException('Could not reach ORCID: '.$exception->getMessage(), 0, $exception);
        }
    }
}
