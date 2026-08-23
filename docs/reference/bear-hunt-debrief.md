# Bear Hunt Debrief reference

Status: Current — 2026-08-23

## Web route

`GET /events/{occurrence}/debrief`

The route is an authenticated Inertia read surface for Alliance-scoped Bear Hunt occurrences. It introduces no public API or webhook contract.

### Preconditions

- authenticated User;
- active Player selected;
- the caller can view the requested Event occurrence;
- Event type slug is `bear-hunt`;
- Event scope is `alliance`.

A request without an active Player returns `409`. A visible non-Bear-Hunt occurrence returns `404`. Normal Events authorization applies before the Debrief payload is composed.

## Inertia payload

Page component: `Operations/Events/BearHuntDebrief`

Top-level props:

- `user`: current UI identity;
- `userTimezone`: display timezone;
- `debrief`: composed read payload.

The `debrief` payload contains:

- `run`: occurrence/Event/Alliance identity, title, start/end and status;
- `summary`: Results availability, total damage, Governor count, accepted report count, attendance summary, Rally summary and unresolved Governor count;
- `governors`: Governor damage/rank with attendance and Rally participation when recorded;
- `personal`: the active Governor's result, attendance and Rally facts;
- `unmatchedGovernors`: manager-only Evidence review summaries with Screenshot Intake handoff links;
- `canReviewEvidence`: whether the current caller may open review actions;
- `previousRun`: immediately preceding completed same-Alliance Bear Hunt, or `null`;
- `comparison`: null-safe current-vs-previous deltas;
- `personalTrend`: bounded chronological personal trend points;
- `allianceTrend`: bounded chronological Alliance trend points;
- `runs`: bounded newest-first run history used for navigation.

## Missing-data contract

`null`/`available=false` means the owner has no recorded evidence for that fact. It must not be rendered as zero.

Rally data is `available=true` only when recorded Rally outcome evidence exists for the occurrence. Once available, a participation count of `0` is a real recorded zero.

A zero previous value does not produce an infinite percentage. The comparison state records that boundary and the UI falls back to an absolute change.

## Review handoff

Unmatched Governor rows are not editable in the Debrief. Their `reviewHref` targets the existing Screenshot Intake workspace for the same occurrence/Evidence. The Debrief never creates Players and never owns identity resolution.

## Mutations

There are no Debrief write routes. All mutations remain at the existing Results, Participation, Rallies and Intelligence/Evidence interfaces.
