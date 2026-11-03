<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Resources;

use VincentAuger\OrcidSdk\Data\Works\BulkRead;
use VincentAuger\OrcidSdk\Data\Works\Work;
use VincentAuger\OrcidSdk\Data\Works\WorkGroups;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Requests\Works\GetWork;
use VincentAuger\OrcidSdk\Requests\Works\GetWorks;
use VincentAuger\OrcidSdk\Requests\Works\ListWorks;

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
     * The record's works as groups: preferred() to display them, summaries() to sync
     */
    public function list(): WorkGroups
    {
        $request = new ListWorks($this->orcid);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * One work in full, by put-code
     */
    public function get(int $putCode): Work
    {
        $request = new GetWork($this->orcid, $putCode);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }

    /**
     * Up to 100 works in full, in one request, keyed by put-code. An unknown put-code is a failure in the result.
     *
     * @param  list<int>  $putCodes
     *
     * @throws \InvalidArgumentException with no put-code or more than Orcid::BULK_LIMIT: split them (ADR 0002)
     */
    public function getMany(array $putCodes): BulkRead
    {
        $request = new GetWorks($this->orcid, $putCodes);

        return $request->createDtoFromResponse($this->connector->sendOrFail($request));
    }
}
