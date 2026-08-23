# GameWorld — KingdomTransfers

Status: Current — Architecture V3

Implementation target: `app/Contexts/GameWorld/KingdomTransfers`

KingdomTransfers owns the domain state and rules for planning and executing Player/Kingdom transfer behavior, including sourced Kingdom Transfer game-planning truth and deterministic eligibility assessment.

## Owned state

KingdomTransfers owns:

- Transfer Plans, participants, readiness transitions, manual blockers and completion state;
- Alliance planning **Transfer Cohorts**;
- Transfer Windows and explicit official phase boundaries;
- official window-scoped Transfer Groups and their Kingdom membership;
- window/target-scoped Kingdom condition observations such as Power Cap and Kingdom classification;
- transfer-specific Governor observations including Power, Transfer Score, available/required Transfer Passes, invitation status and in-game eligibility verification;
- provenance/freshness/conflict semantics for transfer observations;
- deterministic transfer eligibility requirements and next-action calculation;
- transfer audit/outbox events and transfer-specific write invariants.

## Boundary

A transfer may reference Player, Kingdom, Alliance and Evidence identifiers, but the capability does not take ownership of those aggregates. It does not mutate Alliance membership, Kingdom identity, Player identity/progression or Intelligence/Evidence records directly.

Cross-context effects use explicit owner Actions, immutable references/snapshots, scalar identifiers, value objects or composed reads. Evidence may support a transfer observation, but Intelligence/Evidence remains the owner of the source artifact, provenance lifecycle, review and retention. KingdomTransfers validates Evidence references only through the Intelligence/Evidence owner contract: the identifier must belong to the same Alliance, and an `evidence` source is authoritative only when the latest owner review is approved. KingdomTransfers never loads or mutates Evidence models directly.

The capability is not placed in `app/Workflows`; Kingdom transfer is a GameWorld business capability with its own state and invariants.

## Terminology boundary

**Transfer Cohort** is an Alliance-owned coordination bucket inside a Transfer Plan. **Transfer Group** is only the official KingShot event grouping of Kingdoms. See [ADR 0011](../../adr/0011-separate-transfer-planning-cohorts-from-official-transfer-groups.md).

Official Transfer Group membership is scoped to one Transfer Window. No timeless `Kingdom.transfer_group` attribute exists.

## Decision boundary

Eligibility is derived, not persisted. `TransferEligibilityEvaluator` accepts typed, already-scoped inputs and returns a structured assessment containing per-requirement states and next actions. Controllers, jobs, Vue components and generic read models do not reproduce game eligibility rules.

Readiness remains an independent planning concern. A participant may be workflow-ready while game eligibility is blocked or unverified, and may be game-eligible while Alliance planning remains incomplete.

## Evidence boundary

Material eligibility facts carry source/reference and observation time. Mutable Governor observations also carry an explicit validity boundary. Missing, stale, conflicting or non-authoritative information cannot produce `eligible_now`.

Community projects and guides are discovery evidence only. Unpublished or unverified game rules remain evidence-gated instead of being inferred into product truth.
