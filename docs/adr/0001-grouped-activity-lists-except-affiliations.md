# Activity lists are grouped, except affiliations

ORCID merges items from different sources that share a `self` external identifier into groups. Works, fundings
and peer reviews are often merged (the same DOI from a publisher and from OSP), so their `list()` returns groups,
with `preferred()` (one summary per group, for display) and `summaries()` (every summary, for syncing: our own
item may not be the preferred one). Affiliations are grouped by the same rule but in practice never merge (every
group recorded from the sandbox held one item), so they stay a flat list with `displayIndex`, rather than adding a
layer that would almost always be pointless.
