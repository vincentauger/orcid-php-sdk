<?php

declare(strict_types=1);

namespace VincentAuger\OrcidSdk\Contracts;

/**
 * Requests that only exist on the Member API (writes, limited data, webhooks).
 * The Orcid connector refuses to send them through the Public API.
 */
interface RequiresMemberApi {}
