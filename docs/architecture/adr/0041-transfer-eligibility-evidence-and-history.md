# ADR-0041: Bounded transfer eligibility evidence and observation history

Status: Accepted

## Context

A candidate's transfer campaign loaded twenty recent observations and blockers, then filtered the blockers for active state. Older active blockers disappeared, and the UI presented clipped list lengths as totals. Its eligibility query separately loaded every observation in the plan, every condition in the window, and all current group Kingdom memberships. Capacity planning also hydrated complete observation and commitment histories for selected targets.

The existing selectors have meaningful conflict semantics. The latest authoritative current observation does not override another still-current authoritative value: disagreement requires verification. Kingdom condition fields conflict when authoritative records at the latest authoritative timestamp disagree. Taking a fixed number of newest rows would lose those rules. Complete observation history is also a user-visible feature.

## Decision

GameWorld's TransferEligibilityEvidenceQuery selects bounded records that preserve the existing selectors' decisions and provenance. Eligibility and evidence preview share this query; the selectors remain the authoritative interpretation of the selected evidence.

| Projection | Selection and bound |
| --- | --- |
| Participant observations | Scope the requested participants, supported kinds and effective target before ranking. For each kind retain the latest untrusted value, latest non-current authoritative value, and up to two distinct current authoritative values. At most four records per kind, or 48 per participant. |
| Kingdom condition facts | Select only requested target Kingdoms. Retain the latest overall record, latest authoritative record, and at most one conflicting record for each of four numeric fields at the latest authoritative timestamp. At most six records per target. |
| Official groups | Load only groups and Kingdom memberships required by the requested source/target IDs. Current group writers already prevent overlapping current group membership. |
| Capacity facts | Select one latest authoritative condition and capacity observation per target, ordered by timestamp and ID. |
| Planned commitments | Count consuming reservations and special invitations in SQL. Confirmed/issued/accepted commitments are excluded when their update is already reflected by the selected capacity observation. No commitment models are hydrated. |
| Campaign summaries | Count all participant observations and active blockers in SQL. The campaign consumes only these totals; redundant clipped arrays are removed. |
| Observation history | A separate current-authorized query returns 25 records plus a continuation probe, ordered by observation timestamp and ID. Encrypted cursors bind Alliance, plan and participant. |

The Readiness page fetches history only when its section opens, presents page size and first/next navigation, and supports retry after a failed request. This retains all historical records while eligibility and screenshot preview consume bounded factual witnesses. History reads check current Transfer View permission and the participant's exact Alliance/plan relationship on every request. A changed page projection refreshes an open history section without mixing responses from an older projection.

The existing fresh schema gains observation-history and timestamp/ID current-fact indexes. This is an undeployed application; no historical migration, backfill, fallback query or second eligibility evaluator is introduced.

## Alternatives and consequences

A newest-only or fixed-size fact list is rejected because it can hide live conflicts. Loading every row into PHP is rejected because a single candidate can trigger plan/window-wide work. SQL window selection retains the necessary conflicting values and precise source references while bounding application memory and model hydration. Database work still scales with the relevant indexed history needed to establish conflicts; a row-count cap must never turn uncertainty into eligibility.

Twenty-two PHP cases cover all twelve observation kinds against complete selector input, condition conflicts and provenance, more than 500 unrelated records, truthful campaign counts, complete scoped history with deleted boundaries and current authority, each consuming commitment state with/without observations, and scoped group membership. Two desktop/mobile browser cases exercise three history pages, failed continuation and retry. Existing eligibility, capacity reconciliation and screenshot-preview suites remain required. Runtime verification is recorded in the delivery ledger.
