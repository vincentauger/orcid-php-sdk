<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Person\Keyword;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Keywords\GetKeyword;
use VincentAuger\OrcidSdk\Requests\Keywords\ListKeywords;

/**
 * Reads on the keywords of any record: $orcid->record($id)->keywords().
 * Every method throws an OrcidException on failure.
 */
final readonly class ReadOnlyKeywordsResource
{
    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {}

    /**
     * The record's keywords visible to the token, in the order the researcher chose
     *
     * @return list<Keyword>
     */
    public function list(): array
    {
        $request = new ListKeywords($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * One keyword, by put-code
     */
    public function get(int $putCode): Keyword
    {
        $request = new GetKeyword($this->orcid, $putCode);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }
}
