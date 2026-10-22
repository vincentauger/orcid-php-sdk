<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Exceptions;

use Saloon\Http\Response;

/**
 * ORCID answered with an error status.
 *
 * ORCID uses two error body shapes, both parsed here:
 *
 * - API errors: {"response-code", "developer-message", "user-message", "error-code", "more-info"}
 * - OAuth errors (token endpoints, bad or revoked tokens): {"error", "error_description"} or "error-description"
 */
class OrcidRequestException extends OrcidException
{
    final public function __construct(
        public readonly Response $response,
        public readonly ?int $errorCode = null,
        public readonly ?string $developerMessage = null,
        public readonly ?string $userMessage = null,
        public readonly ?string $moreInfo = null,
        public readonly ?string $oauthError = null,
        public readonly ?string $oauthErrorDescription = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($this->buildMessage(), $response->status(), $previous);
    }

    /**
     * Create the exception matching the response status
     */
    public static function fromResponse(Response $response, ?\Throwable $previous = null): self
    {
        $class = match (true) {
            $response->status() === 400 => OrcidValidationException::class,
            $response->status() === 401 => OrcidAuthException::class,
            $response->status() === 403 => OrcidForbiddenException::class,
            $response->status() === 404 => OrcidNotFoundException::class,
            $response->status() === 409 => OrcidConflictException::class,
            $response->status() === 429 => OrcidRateLimitException::class,
            $response->status() >= 500 => OrcidServerException::class,
            default => self::class,
        };

        $body = self::decode($response);

        return new $class(
            response: $response,
            errorCode: is_numeric($body['error-code'] ?? null) ? (int) $body['error-code'] : null,
            developerMessage: self::string($body, 'developer-message'),
            userMessage: self::string($body, 'user-message'),
            moreInfo: self::string($body, 'more-info'),
            oauthError: self::string($body, 'error'),
            // ORCID spells it both ways (error-description on /oauth/userinfo)
            oauthErrorDescription: self::string($body, 'error_description') ?? self::string($body, 'error-description'),
            previous: $previous,
        );
    }

    public function status(): int
    {
        return $this->response->status();
    }

    private function buildMessage(): string
    {
        $detail = $this->developerMessage ?? $this->oauthErrorDescription ?? $this->oauthError;

        $message = sprintf('ORCID request failed with status %d', $this->response->status());

        if ($this->errorCode !== null) {
            $message .= sprintf(' (error %d)', $this->errorCode);
        }

        return $detail === null ? $message : $message.': '.$detail;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decode(Response $response): array
    {
        $body = json_decode($response->body(), true);

        return is_array($body) ? $body : [];
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private static function string(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
