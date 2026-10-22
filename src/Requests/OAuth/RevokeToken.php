<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\OAuth;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;
use VincentAuger\OrcidSdk\Traits\Requests\SendsClientCredentials;

/**
 * Revoke an access or refresh token issued to this client.
 */
final class RevokeToken extends Request implements HasBody
{
    use HasFormBody;
    use SendsClientCredentials;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $token,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/oauth/revoke';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return ['token' => $this->token];
    }
}
