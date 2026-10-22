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
 * Get a 2-legged token for the client itself (client credentials flow),
 * e.g. /read-public for the Public API or /webhook for premium members.
 */
final class RequestClientCredentialsToken extends Request implements HasBody
{
    use HasFormBody;
    use SendsClientCredentials;

    protected Method $method = Method::POST;

    /**
     * @param  Scope[]  $scopes
     */
    public function __construct(
        private readonly array $scopes = [Scope::ReadPublic],
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
            'grant_type' => 'client_credentials',
            'scope' => Scope::encode($this->scopes),
        ];
    }

    public function createDtoFromResponse(Response $response): OrcidToken
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return OrcidToken::fromArray($data);
    }
}
