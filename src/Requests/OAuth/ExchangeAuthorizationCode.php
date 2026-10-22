<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\OAuth;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasFormBody;
use VincentAuger\OrcidSdk\Data\OrcidToken;
use VincentAuger\OrcidSdk\Traits\Requests\SendsClientCredentials;

/**
 * Exchange the authorization code received on the redirect URI for a
 * 3-legged access token (authorization code flow).
 *
 * The redirect URI must be exactly the one used to build the authorization URL.
 */
final class ExchangeAuthorizationCode extends Request implements HasBody
{
    use HasFormBody;
    use SendsClientCredentials;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $code,
        private readonly string $redirectUri,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/oauth/token';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return [
            'grant_type' => 'authorization_code',
            'code' => $this->code,
            'redirect_uri' => $this->redirectUri,
        ];
    }

    public function createDtoFromResponse(Response $response): OrcidToken
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return OrcidToken::fromArray($data);
    }
}
