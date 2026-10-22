<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\OAuth;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasFormBody;
use VincentAuger\OrcidSdk\Data\OrcidToken;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Traits\Requests\SendsClientCredentials;

/**
 * Get a new access token from a refresh token (refresh_token grant).
 *
 * ORCID tokens last about 20 years, so this is mainly used to narrow scopes
 * or rotate a token, optionally revoking the original one.
 */
final class RefreshToken extends Request implements HasBody
{
    use HasFormBody;
    use SendsClientCredentials;

    protected Method $method = Method::POST;

    /**
     * @param  Scope[]|null  $scopes
     */
    public function __construct(
        private readonly string $refreshToken,
        private readonly ?array $scopes = null,
        private readonly bool $revokeOld = false,
        private readonly ?int $expiresIn = null,
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
        return array_filter([
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
            'revoke_old' => $this->revokeOld ? 'true' : 'false',
            'scope' => $this->scopes === null ? null : Scope::encode($this->scopes),
            'expires_in' => $this->expiresIn === null ? null : (string) $this->expiresIn,
        ], static fn (?string $value): bool => $value !== null);
    }

    public function createDtoFromResponse(Response $response): OrcidToken
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return OrcidToken::fromArray($data);
    }
}
