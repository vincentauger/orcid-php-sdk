# A member connector is bound to its record

ORCID's URLs put the iD in every record call (`/v3.0/{orcid-id}/work`), and the SDK first copied that shape:
`Orcid::member($token)->record($id)->...`. But a 3-legged token can only write to (and read limited items of)
the record of the researcher who authorized it, and the `OrcidToken` from the OAuth flow already carries that
iD. Passing it again was duplication, and a chance to pair a token with the wrong record. So `Orcid::member()`
takes the `OrcidToken`, and the sections hang off the connector for that record (`$orcid->works()`), with the
token's scopes checked before sending. Connectors whose token is not tied to a record (the Public API, client
credentials) keep `record($id)`, which only offers reads, because they read any record.
