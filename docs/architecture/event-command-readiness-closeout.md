# Event Command readiness and closeout architecture

Status: Current — Architecture V3

Event Readiness & Closeout is a read-only cross-context composition owned by `app/ReadModels/EventManagement`. It implements the V3 ReadModel rule: owners retain business facts, policy, persistence and writes; the ReadModel authorizes the Event boundary and composes bounded owner projections for presentation.

The product contract is [Event Command — Readiness & Closeout](../product/event-readiness-closeout.md).

## Boundary

Event Command is **not** a bounded context, aggregate, workflow or Event state machine. Do not create `EventReadiness`, `EventCloseout` or a persistence-owned `EventCommand` context.

The ReadModel owns only:

- selection of one occurrence belonging to an already-authorized Event;
- capability-aware invocation of bounded owner queries;
- `complete | needs_attention | warning | unknown | not_applicable` item presentation state;
- derived presentation lifecycle `planning | needs_attention | ready | active | closeout_required | complete`;
- computed blocker/warning counts;
- canonical owner/provenance labels and navigation-only handoffs.

It owns no business write, retry command, readiness flag, closeout flag or duplicated owner record.

## Authorization sequence

```text
authenticated account
  -> active PlayerReference
  -> Operations/Events eventForManage authorization
  -> occurrence constrained by authorized event_id
  -> applicable owner Queries
  -> EventManagement projection
```

An explicit `occurrence` selector is never treated as an independent authorization key. `EventCommandQuery` constrains it by the already-authorized Event before invoking owner projections.

Owner workflows authorize again when a user follows a handoff. Event Command URLs carry navigation identity only.

## Owner dependencies

`ReadModels/EventManagement` may consume these bounded query contracts:

| Owner | Bounded projection |
| --- | --- |
| `Operations/Participation` | occurrence response/registration/waitlist/attendance summary |
| `Operations/Polls` | occurrence planning-poll summary |
| `Operations/Rosters` | occurrence roster capacity/assignment/warning summary |
| `Operations/BattlePlans` | occurrence objective/assignment coverage summary |
| `Operations/Rallies` | occurrence plan and recorded-actual summary |
| `Operations/Results` | occurrence result-completeness summary |
| `Operations/TerritoryPlanning` | immutable attached published-revision validity summary |
| `Alliance/Content` | published Event-linked strategy revision and Content freshness |
| `Operations/Participation/Reminders` | Event-start reminder configuration summary |
| `Communications/Delivery` | occurrence reminder delivery-health summary |
| `Intelligence/Evidence` | Bear Hunt occurrence evidence/review/commit summary |
| `ReadModels/EventAnalysis` | Debrief availability summary |

Owner contexts must not import `App\ReadModels\EventManagement`. Architecture tests enforce this direction.

## Failure semantics

`EventCommandOwnerReader` contains owner-query failures at the presentation boundary. An unavailable applicable owner projection becomes `unknown`; it never becomes zero or complete. Structured warning logs record only the owner key and Event/occurrence identifiers plus exception class, never private Evidence, guide bodies or provider payloads.

This containment is presentation resilience, not domain recovery. Recovery remains in the owner workflow.

## Derived lifecycle

Cancellation remains `Operations/Events` truth and is displayed separately. For a non-cancelled occurrence:

1. active schedule window -> `active`;
2. ended + blocking/unknown closeout item -> `closeout_required`;
3. ended + no blocking closeout item -> `complete`;
4. upcoming + blocking/unknown readiness item -> `needs_attention`;
5. upcoming inside seven-day presentation horizon + no blockers -> `ready`;
6. otherwise -> `planning`.

The lifecycle and counts are recomputed on reads and are not stored.

## Persistence rule

No schema/model may persist:

- `event_ready`;
- `event_complete`;
- readiness/closeout lifecycle;
- Event Command blocker/warning counts;
- copied owner completion state.

`tests/v3/Architecture/EventCommandArchitectureV3Test.php` guards the read-only boundary, dependency direction and prohibited derived persistence tokens.

## Performance rule

Composition is occurrence-level, not per-Governor/per-artifact/per-delivery. Owners provide bounded aggregate projections. `EventCommandQueryBudgetV3Test` compares the selected-occurrence query count as the eligible Governor population grows and fails if query growth becomes proportional.

## Existing architecture decisions

No new ADR is required for this extension. It follows the existing Architecture V3 ReadModel rule and the established composed-management-read approach rather than introducing a new ownership or consistency model. If Event Command later gains durable process state or cross-owner write orchestration, that would be a new architecture decision and requires an ADR before implementation.
