# Phase 13 — Kingshot Event Type Framework reconciliation notes

Status: Current implementation-source-of-truth addendum — 2026-08-28

This addendum records implementation discoveries that refine the canonical Kingshot Event Type Framework contract. It is normative with `kingshot-event-type-framework.md` and must be folded into final product documentation at Phase 13 closeout.

## Scheduling is not an event-profile mechanic

The pre-Phase-13 event catalogue supplied default duration, capacity, registration windows, recurrence policy/frequency/interval, minimum repeat interval and schedule-source assertions. Those values are not workflow applicability and are removed from event-type profile truth.

An application Event occurrence may still have a user-selected start, duration, capacity, registration window and recurrence because those are operational planning facts chosen by an authorized user. An authored Event Template may provide application-owned defaults for those same values.

A Kingshot game-calendar cadence, matchmaking schedule, cooldown, fixed recurrence or minimum repeat interval is a game fact. It must not be derived from event identity or preserved as a generic event-type default without separately qualified evidence. In the absence of such evidence, ordinary Event creation is manual/application-controlled and recurrence is explicitly user-selected or template-selected.

`calendar` is therefore not a Phase 13 workflow dimension. Calendar/scheduling is baseline `Operations/Events` functionality.

## Polls are independent authored workflow

The legacy `polls` capability is not automatically mapped to a Phase 13 event workflow dimension. Polls remain an independently owned Operations capability. Event identity alone may not materialize poll templates or assert that a Kingshot event uses polls. A future event-specific Poll composition requires an explicit product contract; until then no event profile activates Polls.

## Catalogue phases are unsupported mechanics

The legacy `phases` capability materialized catalogue-authored phase names, offsets and durations into occurrences. Those offsets/durations assert event mechanics and are prohibited unless separately evidence-qualified.

Phase 13 removes automatic catalogue phase materialization from event identity. Authored application phases may remain where an authorized workflow explicitly creates them, but a verified event profile does not imply phase timing and candidate/disabled events receive no generated phases.

## Creation-time workflow materialization

`CreateEvent` must not unconditionally materialize specialized workflows. Creation may establish the Event and occurrence identity/schedule. Any automatic owner handoff is permitted only when the resolved verified+enabled event profile includes the corresponding closed workflow dimension.

For the current Bear Hunt profile this permits roster initialization because `roster` is explicitly enabled. Polls and catalogue phases are not dimensions and are not auto-created. Other candidate/profile-disabled event types receive no specialized workflow materialization.

## Legacy capability removal

The old `EventCapability`, `EventTypeCapability`, `EventCapabilityResolver`, `EventCapabilityGuard` and generic scope-configuration capability mutation path are superseded. They must not be retained as aliases or compatibility shims.

Mappings such as registration→participation, rosters→roster, assignments→battle_assignments, rallies→rallies, results→results and territory_planning→territory_plan may inform consumer migration only where the new verified profile already establishes that dimension. They must never be used to auto-promote historical event types.

Legacy `calendar`, `phases`, `polls` and `king_perks` are deliberately not mapped into the closed Phase 13 workflow vocabulary.
