# Kingdoms transfer completion security review

**Increment:** `KINGDOMS-002`  
**Slice:** D / `K2-P5`  
**Status:** Candidate pending protected validation

## Security objective

Slice D introduces the first transfer-planning operation that may deliberately mutate the accepted alliance roster. The boundary is therefore narrower than readiness: completion is an explicit manager-confirmed real-world handoff, never an inferred or automatic consequence of `confirmed` readiness.

## Authorization and tenant boundary

Completion requires the existing authenticated/verified active-Alliance context, `kingdoms.manage`, and recent password confirmation.

The action re-resolves and locks:

1. the active Alliance;
2. the submitted transfer plan beneath that Alliance;
3. the plan home Kingdom reference;
4. the submitted participant beneath that Alliance and plan; and
5. any submitted incoming existing roster result beneath the same Alliance.

Coordinator assignment confers no completion authority. Platform/global identity references do not weaken Alliance tenancy.

Cross-alliance plan, participant and roster identifiers fail closed.

## Lifecycle gate

Completion is permitted only when the plan is `locked` and the participant is explicitly `confirmed` and not withdrawn.

This creates a deliberate phase boundary:

- Draft/Open: planning, groups, readiness and blockers may change;
- Locked: planning is frozen and explicit real-world completion may be recorded;
- Closed: all non-withdrawn participants already have completion records.

Home-Kingdom drift fails completion closed. The action never silently reconciles or retargets a stale plan.

## Idempotency and concurrency

`transfer_completions.transfer_participant_id` is unique.

The completion action serializes Alliance → plan → participant and checks for an existing completion before any delegated roster side effect. A retry returns the existing record without repeating roster mutation or completion events.

Plan closing uses the same Alliance → plan lock order and refuses close while an active participant lacks completion. A close therefore cannot race past an in-flight handoff.

## Incoming identity safety

There is no display-name-only roster merge.

A manager may either:

- create a new accepted roster result through `SaveRosterEntry`; or
- explicitly select an existing active/tracked same-alliance roster entry.

When the participant has a stable game-player identifier, an explicitly selected roster result must carry the same identifier. Existing membership linkage cannot be replaced with a different participant membership.

Existing accepted roster name, game role, state, joined date and manager notes are retained when linking an existing result.

The incoming planning/source `Player` is not moved between Kingdoms. The resulting roster entry is recorded on the completion instead.

## Outgoing and staying safety

Outgoing handoff re-resolves the captured same-alliance roster entry and validates its captured neutral-player binding before calling accepted `MarkRosterEntryLeft`. Snapshot history and neutral identity are not deleted or rewritten.

Staying completion re-resolves the same binding but calls no roster lifecycle mutation. It records only the transfer outcome.

## Snapshot integrity

Completion never creates a `PlayerSnapshot`, derives power, copies stale power, or rewrites existing observation history. Snapshot creation remains exclusively within the accepted observation contract.

## Privacy

Ordinary members may see safe completion time as part of approved transfer outcome presentation.

Manager-only completion data includes:

- completing actor;
- completion record identity;
- resulting roster-entry identity; and
- richer handoff provenance.

Private participant/group notes and blocker summary/details are not copied into completion audit/outbox metadata.

## Event boundary

A material completion emits internal `kingdoms.transfer_participant_completed` audit and transactional-outbox evidence. Delegated roster actions emit their accepted roster events only when they materially change roster state.

`kingdoms.*` remains excluded from generic external webhook fan-out. Slice D creates no public completion API or webhook contract.

## Abuse and automation review

Slice D deliberately excludes:

- bulk “complete all” actions;
- automatic completion when readiness becomes `confirmed`;
- inferred transfer eligibility;
- transfer-pass/resource optimization;
- automatic destination/stay/leave decisions;
- automated in-game transfer execution;
- cross-alliance transfer visibility;
- scraping/OCR/bots/undocumented APIs; and
- punitive ranking/scoring.

Each completion is a separate attributable manager action with explicit real-world confirmation language.

## Required validation

Protected acceptance evidence must cover:

- incoming create/link behavior and no display-name-only merge;
- preservation of existing roster/private fields on explicit link;
- outgoing mark-left delegation;
- staying no-op roster behavior;
- retry/idempotency and event counts;
- failure/rollback on selected roster identity mismatch;
- cross-tenant submitted IDs;
- home-Kingdom drift;
- password confirmation and permission gates;
- close-before-completion rejection;
- no fabricated snapshots;
- member-safe versus manager-only completion presentation;
- migration rollback/reapply; and
- full repository security/quality gates.
