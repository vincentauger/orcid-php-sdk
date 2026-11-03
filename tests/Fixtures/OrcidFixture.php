<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Saloon\Data\RecordedResponse;
use Saloon\Http\Faking\Fixture;

/**
 * Saloon fixture that strips credentials and identifiers before a response
 * recorded against the ORCID sandbox is written to disk.
 *
 * - every ORCID iD (body and headers, e.g. Location) becomes PLACEHOLDER_ID
 * - every client id (e.g. source-client-id on activities) becomes PLACEHOLDER_CLIENT_ID
 * - OAuth tokens and cookies are replaced
 * - list responses only keep the items created by tests (TEST_MARKER), not the other
 *   items on the sandbox record: see TEST_MARKER_FIELDS and keepTestItems()
 *
 * Profile data (names, biography, keywords, ...) is kept: the sandbox record only
 * holds test content. Never record against a record with real personal data.
 */
final class OrcidFixture extends Fixture
{
    /**
     * ORCID's documented example iD (Josiah Carberry)
     */
    public const string PLACEHOLDER_ID = '0000-0002-1825-0097';

    public const string PLACEHOLDER_CLIENT_ID = 'APP-0000000000000000';

    public const string REDACTED = 'Redacted';

    /**
     * Every item created by a test starts its department name (or title) with this
     */
    public const string TEST_MARKER = 'orcid-php-sdk';

    /**
     * The summaries kept in fixtures only when created by tests, by key, with their field that starts with
     * TEST_MARKER (plain or wrapped as {"value": ...}, at any depth). An element of any list (e.g. a group)
     * that holds such summaries is dropped unless one of them is a test item. A new section adds its own here.
     *
     * @var array<string, string>
     */
    public const array TEST_MARKER_FIELDS = [
        'employment-summary' => 'department-name',
        'education-summary' => 'department-name',
        'qualification-summary' => 'department-name',
        'invited-position-summary' => 'department-name',
        'distinction-summary' => 'department-name',
        'membership-summary' => 'department-name',
        'service-summary' => 'department-name',
        'work-summary' => 'title',
        'funding-summary' => 'title',
        // a summary has no subject name: test peer reviews mark their review identifier
        'peer-review-summary' => 'external-id-value',
        'research-resource-summary' => 'title',
    ];

    /**
     * The person items kept in fixtures only when created by tests, by the key of their list, with their field that
     * starts with TEST_MARKER. Unlike summaries, each item of the list is kept or dropped on its own. Addresses are
     * not filtered: an address has only a country, nothing to mark.
     *
     * @var array<string, string>
     */
    public const array TEST_MARKER_ITEMS = [
        'keyword' => 'content',
        'other-name' => 'content',
        // test websites are named; the URL itself can't start with the marker
        'researcher-url' => 'url-name',
        'external-identifier' => 'external-id-value',
        // the record summary's affiliations have no department name: test affiliations there mark their role title
        'employment' => 'role',
        'professional-activity' => 'role',
        'education-qualification' => 'role',
    ];

    /**
     * The person lists whose items added by the researcher (on the website, not through a client) are kept too: the
     * hand-made test content of issue #3. The researcher's own keywords never end up in fixtures.
     *
     * @var list<string>
     */
    public const array HAND_ADDED_ITEMS = ['other-name', 'researcher-url'];

    private const string ORCID_ID_PATTERN = '/\d{4}-\d{4}-\d{4}-\d{3}[\dX]/';

    private const string CLIENT_ID_PATTERN = '/APP-[A-Z0-9]{16}/';

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

        $body = $this->keepTestGroups($body);

        $recordedResponse->data = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return $recordedResponse;
    }

    /**
     * Remove list elements holding summaries not created by tests, at any depth (lists and the activities summary)
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function keepTestGroups(array $data): array
    {
        // a whole group first, then the summaries inside it
        if (array_is_list($data)) {
            $data = array_values(array_filter($data, $this->isKept(...)));
        }

        foreach ($data as $key => $value) {
            $field = is_string($key) ? self::TEST_MARKER_ITEMS[$key] ?? null : null;

            if ($field !== null && is_array($value) && array_is_list($value)) {
                $data[$key] = array_values(array_filter(
                    $value,
                    // person items are objects: a plain string is an expanded search result's other name, kept since
                    // search fixtures only match the test record
                    fn (mixed $item): bool => ($key === 'other-name' && is_string($item))
                            || $this->isMarked($field, $item)
                            || (in_array($key, self::HAND_ADDED_ITEMS, true) && $this->isAddedByResearcher($item)),
                ));
            } elseif (is_array($value)) {
                $data[$key] = $this->keepTestGroups($value);
            }
        }

        return $data;
    }

    /**
     * Whether a list element is kept: it holds no filtered summary, or at least one created by a test
     */
    private function isKept(mixed $element): bool
    {
        $summaries = $this->summaries($element);

        if ($summaries === []) {
            return true;
        }

        foreach ($summaries as [$field, $summary]) {
            if ($this->isMarked($field, $summary)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a field of the value starts with TEST_MARKER (plain or wrapped as {"value": ...}, at any depth)
     */
    private function isMarked(string $field, mixed $value): bool
    {
        $pattern = '/"'.preg_quote($field, '/').'":(?:\{"value":)?"'.preg_quote(self::TEST_MARKER, '/').'/';

        return preg_match($pattern, (string) json_encode($value, JSON_UNESCAPED_SLASHES)) === 1;
    }

    /**
     * Whether a person item was added by the researcher, not through a client
     */
    private function isAddedByResearcher(mixed $item): bool
    {
        return is_array($item)
            && is_array($item['source'] ?? null)
            && ($item['source']['source-client-id'] ?? null) === null
            && ($item['source']['source-orcid'] ?? null) !== null;
    }

    /**
     * The filtered summaries in a value, at any depth, each with its marker field
     *
     * @return list<array{string, mixed}>
     */
    private function summaries(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $summaries = [];

        foreach ($value as $key => $child) {
            $field = is_string($key) ? self::TEST_MARKER_FIELDS[$key] ?? null : null;

            if ($field === null) {
                array_push($summaries, ...$this->summaries($child));
            } elseif (is_array($child) && array_is_list($child)) {
                // works and fundings: a list of summaries under one key
                foreach ($child as $summary) {
                    $summaries[] = [$field, $summary];
                }
            } else {
                $summaries[] = [$field, $child];
            }
        }

        return $summaries;
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
}
