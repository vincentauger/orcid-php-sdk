# ORCID PHP SDK

A PHP client for ORCID's v3.0 API, used by OSP and published for anyone. It follows ORCID's own vocabulary,
so its users can read ORCID's documentation alongside it.

## Records

**ORCID iD**:
The identifier of one researcher, e.g. `0000-0002-1825-0097`.
_Avoid_: ORCID (alone), ORCID number

**Record**:
Everything ORCID holds about one ORCID iD.
_Avoid_: Profile, account

**Section**:
One part of a record. A **person section** describes the researcher (names, keywords, websites, emails,
countries, external identifiers); an **activity section** lists what they did (works, fundings, peer reviews,
research resources and the seven affiliation sections).
_Avoid_: Category, field, endpoint

**Item**:
One entry in a section, identified by its put-code and added by exactly one source.
_Avoid_: Entry, activity, record (for a single item)

**Put-code**:
ORCID's identifier for an item, unique within its record.
_Avoid_: Item id, ORCID id (for an item)

**Source**:
Who added an item: a client (an ORCID integration, e.g. OSP or Crossref) or the researcher themselves. Only
the source can update or delete its item.
_Avoid_: Owner, author, creator

**Visibility**:
Who may read an item: everyone (public), trusted parties (limited), signed-in ORCID users (registered-only,
legacy) or only the researcher (private).
_Avoid_: Privacy, access level

## Activities

**Affiliation**:
An item in one of the seven affiliation sections: employment, education, qualification, invited position,
distinction, membership, service.
_Avoid_: Employment (for affiliations in general), position

**Work**:
An item in the works section: a research output (article, data set, software, ...), identified by its `self`
external identifier.
_Avoid_: Publication, output

**Funding**:
An item in the fundings section: a grant, contract, award or salary award, from a funder (an organization),
usually identified by its grant number.
_Avoid_: Grant (for fundings in general), award

**Peer review**:
An item in the peer reviews section: a review the researcher did (as reviewer, editor, chair, ...) of a subject (an
article, a grant proposal, ...), within a review group and convened by an organization, usually the publisher.
_Avoid_: Review (alone, for the item), referee report

**Research resource**:
An item in the research resources section: access a researcher was granted to resources (a vessel, a facility, a
collection, a service, ...) through a **proposal**, with the **resource items** it gave access to. Added by
infrastructure providers.
_Avoid_: Resource (alone, for the item), booking, allocation

**Proposal**:
The part of a research resource that says what access was granted for: a title, its hosts, identifiers and dates.
ORCID groups research resources by the proposal's `self` external identifier.
_Avoid_: Application, project

**Resource item**:
One resource a research resource gave access to: a name, a type (infrastructures, collections, equipment, services),
its hosts and identifiers.
_Avoid_: Resource (alone), asset

**Host**:
An organization that grants access to research resources (on a proposal) or provides one (on a resource item).
_Avoid_: Provider, owner

**Review group**:
What a peer review was done for, usually a journal: ORCID's group id, e.g. `issn:0706-652X`. ORCID creates an ISSN
group on first use; others come from its group-id registry. Not a **Group**: one review group holds many groups.
_Avoid_: Group id (in prose), journal (for review groups in general)

**Contributor**:
A person credited on a work or a funding: a credit name and, when they authenticated it, their ORCID iD.
_Avoid_: Author (for contributors in general), collaborator

**External identifier**:
An identifier from another system attached to an item or a record, e.g. a DOI or a grant number. With the
relationship `self`, it identifies the item itself.
_Avoid_: External id (in prose), PID

**Group**:
Items from different sources that ORCID considers the same thing, because they share a `self` external
identifier.
_Avoid_: Duplicate set, cluster

**Preferred summary**:
The summary within a group that the researcher chose to show, and the one ORCID displays.
_Avoid_: Primary, main version, canonical

**Summary**:
The short form of an item returned in lists; the full item comes from reading it by put-code.
_Avoid_: Preview, list item

**Activities summary**:
The summaries of every activity section of a record, read in one call.
_Avoid_: Activities (alone), record summary
