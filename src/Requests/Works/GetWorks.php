<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Works;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use VincentAuger\OrcidSdk\Data\Works\BulkRead;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Get up to 100 works in full (GET /{orcid}/works/{put-code,put-code,...}). ORCID answers 200 with one item per
 * put-code, in order: the work, or an error (400, error 9034, for an unknown put-code).
 */
final class GetWorks extends Request
{
    use HasRecordEndpoint;

    protected Method $method = Method::GET;

    /**
     * @var list<int>
     */
    private readonly array $putCodes;

    /**
     * @param  list<int>  $putCodes  Repeated put-codes are read once
     *
     * @throws \InvalidArgumentException when there are none or more than Orcid::BULK_LIMIT (ADR 0002)
     */
    public function __construct(
        private readonly string $orcid,
        array $putCodes,
    ) {
        $this->putCodes = array_values(array_unique($putCodes));

        if ($this->putCodes === [] || count($this->putCodes) > Orcid::BULK_LIMIT) {
            throw new \InvalidArgumentException(sprintf(
                'ORCID reads 1 to at most %d works per request, %d given: split them into several calls',
                Orcid::BULK_LIMIT,
                count($this->putCodes),
            ));
        }
    }

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'works/'.implode(',', $this->putCodes));
    }

    public function createDtoFromResponse(Response $response): BulkRead
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return BulkRead::fromArray($data, $this->putCodes);
    }
}
