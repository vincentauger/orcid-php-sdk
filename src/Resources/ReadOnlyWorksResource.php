<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Works\Work;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Works\GetWork;

/**
 * Reads on the works of any record: $orcid->record($id)->works().
 * Every method throws an OrcidException on failure.
 */
final readonly class ReadOnlyWorksResource
{
    public function __construct(
        private Orcid $connector,
        private string $orcid,
    ) {}

    /**
     * One work in full, by put-code
     */
    public function get(int $putCode): Work
    {
        $request = new GetWork($this->orcid, $putCode);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }
}
