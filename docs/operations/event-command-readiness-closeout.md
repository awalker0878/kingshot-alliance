# Event Command readiness and closeout operations

Status: Current

This runbook covers the read-only Event Command composition on Event Management. Domain recovery remains in the owning capability; Event Command itself has no repair/write command.

## Runtime signals

A successful composition emits repository-standard debug logging:

```text
event_command.rendered
  event_id
  occurrence_id
  state
  blocker_count
  warning_count
  duration_ms
```

An owner projection that throws is contained and emits:

```text
event_command.owner_projection_unavailable
  owner
  event_id
  occurrence_id
  exception
```

Do not add Evidence OCR contents, screenshots, private guide bodies, provider payloads, recipient data or other source contents to these logs.

## Expected failure behavior

If one applicable owner projection is unavailable:

1. Event Management continues rendering where possible;
2. that owner dimension becomes `unknown` rather than zero/complete;
3. an applicable blocking unknown keeps readiness/closeout blocked;
4. the normal owner handoff remains the recovery destination when it can be safely constructed;
5. reloading Event Management re-runs the read composition after owner recovery.

There is no Event Command replay queue because Event Command owns no durable process.

## Diagnosing an unexpected state

1. Confirm the selected `occurrence` belongs to the Event shown on the management page.
2. Confirm underlying `Operations/Events` occurrence schedule/status and cancellation truth.
3. Inspect `event_command.rendered` for state and blocker/warning counts.
4. Inspect any `event_command.owner_projection_unavailable` event and identify the owner key.
5. Use the canonical owner workflow/query diagnostics for that owner; do not patch Event Command-derived state in the database.
6. Re-read Event Management after the owner issue is corrected.

If an owner fact is correct but Event Command classification is wrong, treat that as a composition defect. Update the `/docs/product/event-readiness-closeout.md` rule first if the intended behavior is missing or ambiguous, then change the composition and tests.

## Query budget

`tests/v3/ReadModels/EventManagement/EventCommandQueryBudgetV3Test.php` protects the selected-occurrence composition from query-count growth as eligible Governor population increases. A regression should be resolved by adding/batching a bounded owner projection, not by caching/persisting derived Event Command truth.

Owner summaries must avoid per-Governor, per-Evidence and per-delivery retrieval loops. Query payload row counts may naturally grow; query count must remain bounded.

## Visual/accessibility verification

`tests/v3/Visual/EventCommand.spec.ts` covers desktop and mobile Event Command cards for both closeout-required and ready states. It verifies:

- primary state text is visible without relying on color;
- owner attribution and canonical action are visible;
- native occurrence selector is accessible;
- the card has no horizontal overflow;
- rendered visual fingerprints remain stable after dynamic timestamps are normalized.

The component also uses textual item states and an `aria-live="polite"` summary for blocker/warning changes.

## Release verification

Event Command is not complete until one immutable candidate passes the repository gates applicable to the change, including:

- PHP test suite;
- Pint;
- PHPStan/static analysis with no baseline;
- frontend lint, format, type check and production build;
- Architecture V3 tests;
- Event Command behavior/isolation/query-budget tests;
- Playwright visual regression;
- CodeQL;
- dependency review;
- container/release checks configured by the repository;
- staging and backup/restore checks when the release workflow requires them.

Do not mark the product delivery ledger complete from an individual green job. Record completion only after the immutable candidate and required downstream environment verification are reconciled.

## Recovery ownership examples

| Event Command item | Recovery owner/workflow |
| --- | --- |
| missing Event reminder | Event reminder management |
| delivery failure | Communications delivery/retry workflow |
| unanswered/attendance missing | Participation |
| roster gap | Rosters |
| Battle Plan assignment gap | BattlePlans |
| Rally plan/actual gap | Rallies |
| Territory validation | TerritoryPlanning using the referenced published revision |
| stale/missing Alliance strategy | Alliance/Content |
| missing Results | Results |
| Evidence review/match/commit failure | Screenshot Intake / Intelligence/Evidence |
| Debrief availability | EventAnalysis read path after required owner facts exist |

Operators must not create database fixes for Event Command lifecycle or counts because those values do not exist as authoritative persistence.
