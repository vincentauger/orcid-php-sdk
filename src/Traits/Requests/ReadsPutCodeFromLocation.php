<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Traits\Requests;

use Saloon\Http\Response;

/**
 * Create requests: ORCID answers 201 with an empty body, and the new put-code
 * is the last segment of the Location header
 */
trait ReadsPutCodeFromLocation
{
    /**
     * The put-code of the new item
     */
    public function createDtoFromResponse(Response $response): int
    {
        $location = $response->header('Location');

        if (! is_string($location) || preg_match('#/(\d+)/?$#', $location, $matches) !== 1) {
            throw new \UnexpectedValueException('ORCID did not return the put-code of the new item (no Location header)');
        }

        return (int) $matches[1];
    }
}
