# ADR-0039: Complete bounded member governance history

Status: Accepted

## Context

Member governance history loaded the latest 500 Alliance audit events and filtered their metadata in memory, returning at most 100 records with no continuation. A busy Alliance could therefore hide all relevant history for a member behind unrelated activity. The member page and Member Capability Profile shared this query, but admission lived only in adapters.

## Decision

MembershipGovernanceHistoryQuery requires the current viewer, Alliance and target Player. GovernanceHistoryAccess centralizes current MembershipManage/RoleManage/Manage admission for both governance queries and the profile's optional section. The target must have current or former Membership/roster association, or a supported audit fact in that Alliance. An unrelated Player cannot be exposed through the member page.

The query constrains Alliance, the supported event whitelist and all five supported target metadata keys in SQL before ordering or limiting. It returns a PageSlice with 50 records by default and a maximum of 100. Created-at and ID provide deterministic descending order. Authenticated cursors bind Alliance, target and the complete boundary; deletion of the boundary does not require re-reading it. The fresh audit schema adds partial expression indexes for each non-null target key, scoped by Alliance with chronological tie-breakers.

The member page exposes next/first-page navigation through the shared localized pagination component. Member Capability Profile deliberately requests a 12-record preview from the same authorized query and links to the complete history; its label describes the shown count rather than implying a lifetime total. There is no viewer-less or list-only compatibility path.

## Consequences

Unrelated activity cannot hide a member's retained history. At most the requested page plus one record is hydrated, and former-member evidence remains available without requiring active membership of the target. Twelve PHP cases cover all five metadata keys, more than 500 unrelated events, 101 tied records, changed/deleted boundaries, scoped cursors, current revocation, HTTP continuation, profile composition and unrelated-player denial. Two browser cases verify the profile handoff and 50/5/first-page navigation on desktop and mobile. The explicit preview label was reviewed in desktop/mobile CI traces and only its two profile fingerprints were updated. The same browser run exposed a banner link to the nonexistent /alliance/roster/history route; that link is removed, and individual Governor rows remain the entry to roster/profile history.
