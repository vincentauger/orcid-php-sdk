<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Requests\Works;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use VincentAuger\OrcidSdk\Contracts\RequiresMemberApi;
use VincentAuger\OrcidSdk\Contracts\RequiresScopes;
use VincentAuger\OrcidSdk\Data\Works\BulkResult;
use VincentAuger\OrcidSdk\Data\Works\WorkInput;
use VincentAuger\OrcidSdk\Enums\Scope;
use VincentAuger\OrcidSdk\Orcid;
use VincentAuger\OrcidSdk\Traits\Requests\HasRecordEndpoint;

/**
 * Add up to 100 works (POST /{orcid}/works). ORCID answers 200 with one item per work, in order: the created
 * work, or an error (409, error 9021, for a duplicate `self` external identifier, even within the batch).
 * A body ORCID can't read at all (e.g. an unknown type) fails the whole request: 400, error 9047.
 */
final class CreateWorks extends Request implements HasBody, RequiresMemberApi, RequiresScopes
{
    use HasJsonBody;
    use HasRecordEndpoint;

    protected Method $method = Method::POST;

    /**
     * @param  list<WorkInput>  $inputs
     *
     * @throws \InvalidArgumentException when there are none or more than Orcid::BULK_LIMIT (ADR 0002)
     */
    public function __construct(
        private readonly string $orcid,
        private readonly array $inputs,
    ) {
        if ($inputs === [] || count($inputs) > Orcid::BULK_LIMIT) {
            throw new \InvalidArgumentException(sprintf(
                'ORCID adds 1 to at most %d works per request, %d given: split them into several calls',
                Orcid::BULK_LIMIT,
                count($inputs),
            ));
        }
    }

    public function resolveEndpoint(): string
    {
        return $this->recordEndpoint($this->orcid, 'works');
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return ['bulk' => array_map(static fn (WorkInput $input): array => ['work' => $input->toArray()], $this->inputs)];
    }

    public function requiredScopes(): array
    {
        return [Scope::ActivitiesUpdate];
    }

    public function createDtoFromResponse(Response $response): BulkResult
    {
        /** @var array<string, mixed> $data */
        $data = $response->json();

        return BulkResult::fromArray($data);
    }
}
