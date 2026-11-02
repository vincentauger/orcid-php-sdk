# A member connector is bound to its record

ORCID's URLs put the iD in every record call (`/v3.0/{orcid-id}/work`), and the SDK first copied that shape:
`Orcid::member($token)->record($id)->...`. But a 3-legged token can only write to (and read limited items of)
the record of the researcher who authorized it, so passing the iD on every call was duplication, and a chance
to pair a token with the wrong record. So `Orcid::member(accessToken:, orcid:, scopes:)` takes the iD once, from
the values an integration stores (not an `OrcidToken`, which holds fields the connector doesn't use), and the
sections hang off the connector for that record (`$orcid->works()`). Every connector keeps `record($id)`, which
only offers reads: a member connector without an `orcid` (e.g. a client-credentials token, for the Member API's
higher limits) reads any record that way, and its own-record calls throw.
