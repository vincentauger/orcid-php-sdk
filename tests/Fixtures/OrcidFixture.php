<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Saloon\Data\RecordedResponse;
use Saloon\Http\Faking\Fixture;

/**
 * Saloon fixture that strips credentials and personal data before a
 * response recorded against the ORCID sandbox is written to disk.
 *
 * - every ORCID iD (body and headers, e.g. Location) becomes PLACEHOLDER_ID
 * - every client id (e.g. source-client-id on activities) becomes PLACEHOLDER_CLIENT_ID
 * - OAuth tokens and the token owner's name are replaced
 * - personal values in person sections (names, emails, keywords, ...) are replaced,
 *   keeping the JSON structure so the fixtures still parse
 */
final class OrcidFixture extends Fixture
{
    /**
     * ORCID's documented example iD (Josiah Carberry)
     */
    public const string PLACEHOLDER_ID = '0000-0002-1825-0097';

    public const string PLACEHOLDER_CLIENT_ID = 'APP-0000000000000000';

    public const string REDACTED = 'Redacted';

    private const string ORCID_ID_PATTERN = '/\d{4}-\d{4}-\d{4}-\d{3}[\dX]/';

    private const string CLIENT_ID_PATTERN = '/APP-[A-Z0-9]{16}/';

    /**
     * Person sections whose string values are personal data
     */
    private const array PERSONAL_SECTIONS = [
        'name', 'given-names', 'family-name', 'credit-name', 'other-names',
        'biography', 'researcher-urls', 'emails', 'addresses', 'keywords',
        'external-identifiers', 'source-name',
    ];

    /**
     * Leaf keys holding the actual personal values inside those sections
     */
    private const array PERSONAL_LEAVES = ['value', 'content', 'email', 'url-name'];

    protected function defineSensitiveHeaders(): array
    {
        return [
            'Set-Cookie' => self::REDACTED,
        ];
    }

    protected function defineSensitiveJsonParameters(): array
    {
        return [
            'access_token' => self::REDACTED,
            'refresh_token' => self::REDACTED,
            'id_token' => self::REDACTED,
            'given_name' => self::REDACTED,
            'family_name' => self::REDACTED,
        ];
    }

    protected function defineSensitiveRegexPatterns(): array
    {
        return [
            self::ORCID_ID_PATTERN => self::PLACEHOLDER_ID,
            self::CLIENT_ID_PATTERN => self::PLACEHOLDER_CLIENT_ID,
        ];
    }

    protected function beforeSave(RecordedResponse $recordedResponse): RecordedResponse
    {
        $recordedResponse->headers = $this->redactHeaderIds($recordedResponse->headers);

        $body = json_decode($recordedResponse->data, true);

        if (! is_array($body)) {
            return $recordedResponse;
        }

        // OAuth token and userinfo responses return the owner's name as a top-level string
        // (on records, the top-level `name` is an object handled by redactPersonalData)
        if (is_string($body['name'] ?? null)) {
            $body['name'] = self::REDACTED;
        }

        $recordedResponse->data = json_encode($this->redactPersonalData($body), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return $recordedResponse;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private function redactHeaderIds(array $headers): array
    {
        array_walk_recursive($headers, function (mixed &$value): void {
            if (is_string($value)) {
                $value = (string) preg_replace(
                    [self::ORCID_ID_PATTERN, self::CLIENT_ID_PATTERN],
                    [self::PLACEHOLDER_ID, self::PLACEHOLDER_CLIENT_ID],
                    $value,
                );
            }
        });

        return $headers;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redactPersonalData(array $data, bool $inPersonalSection = false): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redactPersonalData($value, $inPersonalSection || in_array($key, self::PERSONAL_SECTIONS, true));
            } elseif ($inPersonalSection && is_string($value) && in_array($key, self::PERSONAL_LEAVES, true)) {
                $data[$key] = self::REDACTED;
            }
        }

        return $data;
    }
}
