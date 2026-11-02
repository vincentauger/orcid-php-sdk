# One method call sends one HTTP request

ORCID's bulk work calls (`GET /works/{put-codes}`, `POST /works`) take at most 100 items. Above that, the SDK
throws an `InvalidArgumentException` instead of splitting the batch into several requests. The SDK leaves retries
to its callers (e.g. queue jobs), and a method that sends several requests can fail halfway, with some batches
written and others not, which no caller can retry safely. Callers split their batches themselves.
