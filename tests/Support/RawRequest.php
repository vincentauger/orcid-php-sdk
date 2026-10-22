<?php

declare(strict_types=1);

namespace Tests\Support;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Generic request used to replay the A1 sandbox fixtures, which were
 * recorded before the SDK had typed requests.
 */
final class RawRequest extends Request implements HasBody
{
    use HasJsonBody;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        protected Method $method = Method::GET,
        private readonly string $endpoint = '/',
        private readonly array $payload = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
