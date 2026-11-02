<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Data\Common;

use VincentAuger\OrcidSdk\Support\Json;

/**
 * Who added an item: a member client, or the record owner.
 * Only the source of an item can update or delete it.
 *
 * The assertion origin is set when the source added the item on behalf of
 * someone else (token delegation, ORCID's Search & Link wizards): the source
 * is then the client that sent it, and the origin who asserted it.
 */
final readonly class Source
{
    public function __construct(
        public ?string $clientId = null,
        public ?string $orcid = null,
        public ?string $name = null,
        public ?string $assertionOriginClientId = null,
        public ?string $assertionOriginOrcid = null,
        public ?string $assertionOriginName = null,
    ) {}

    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(
            clientId: Json::string(Json::object($data, 'source-client-id') ?? [], 'path'),
            orcid: Json::string(Json::object($data, 'source-orcid') ?? [], 'path'),
            name: Json::string($data, 'source-name'),
            assertionOriginClientId: Json::string(Json::object($data, 'assertion-origin-client-id') ?? [], 'path'),
            assertionOriginOrcid: Json::string(Json::object($data, 'assertion-origin-orcid') ?? [], 'path'),
            assertionOriginName: Json::string($data, 'assertion-origin-name'),
        );
    }

    /**
     * Whether the item was added by the given client (e.g. your own client id, to find your items)
     */
    public function isClient(string $clientId): bool
    {
        return $this->clientId === $clientId;
    }

    /**
     * Whether the record owner added the item themselves
     */
    public function isRecordOwner(): bool
    {
        return $this->orcid !== null && $this->clientId === null;
    }
}
