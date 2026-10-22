<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\OAuth;

use Saloon\Contracts\Authenticator;
use Saloon\Enums\Method;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\UserInfo;

/**
 * OpenID Connect user info for an access token. Authenticated with the token
 * itself, not the client credentials.
 */
final class GetUserInfo extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $accessToken,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/oauth/userinfo';
    }

    protected function defaultAuth(): Authenticator
    {
        return new TokenAuthenticator($this->accessToken);
    }

    public function createDtoFromResponse(Response $response): UserInfo
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return UserInfo::fromArray($data);
    }
}
