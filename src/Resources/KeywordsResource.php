<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Person\Keyword;
use VincentAuger\OrcidSdk\Data\Person\KeywordInput;
use VincentAuger\OrcidSdk\Exceptions\OrcidConflictException;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Keywords\CreateKeyword;
use VincentAuger\OrcidSdk\Requests\Keywords\DeleteKeyword;
use VincentAuger\OrcidSdk\Requests\Keywords\UpdateKeyword;

/**
 * Keywords of the record a member connector is bound to: $orcid->keywords().
 * Writes need the /person/update scope. Every method throws an OrcidException on failure.
 */
final readonly class KeywordsResource
{
    private ReadOnlyKeywordsResource $readOnly;

    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {
        $this->readOnly = new ReadOnlyKeywordsResource($connector, $orcid);
    }

    /**
     * The record's keywords visible to the token, in the order the researcher chose
     *
     * @return list<Keyword>
     */
    public function list(): array
    {
        return $this->readOnly->list();
    }

    /**
     * One keyword, by put-code
     */
    public function get(int $putCode): Keyword
    {
        return $this->readOnly->get($putCode);
    }

    /**
     * @return int The put-code of the new item: store it to update or delete the item later
     *
     * @throws OrcidConflictException when this client already added a keyword with the same content
     *                                (`isDuplicate()`; ORCID does not give its put-code)
     */
    public function create(KeywordInput $input): int
    {
        $request = new CreateKeyword($this->orcid, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Replace a keyword created by this client
     *
     * @throws OrcidConflictException when this client already added another keyword with the new content
     */
    public function update(int $putCode, KeywordInput $input): Keyword
    {
        $request = new UpdateKeyword($this->orcid, $putCode, $input);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    public function delete(int $putCode): void
    {
        $this->connector->sendOrFail(new DeleteKeyword($this->orcid, $putCode));
    }
}
