# Bear Hunt Debrief

Status: Implemented current state — 2026-08-23

Bear Hunt Debrief is the first complete post-Event analysis vertical slice for Bear Hunt. It turns already-owned Event, Result, Participation, Rally and reviewed Evidence facts into a useful after-action view for Governors and Alliance leadership. It is deliberately **not** a `BearHunt` bounded context.

This document is the source of truth for the implemented capability. A requirement remains complete only while its backend behavior, authorization, data semantics, UX, accessibility, localization, observability, tests and applicable visual/release evidence remain complete.

## Product outcome

After a Bear Hunt occurrence, an authorized Governor can open a debrief and understand:

- the run date/status and recent run history;
- total recorded Alliance damage;
- Governor damage and rank;
- attendance;
- recorded rally participation where the application has authoritative participation evidence;
- unresolved Governor observations that still need review in Screenshot Intake;
- comparison with the immediately preceding completed Bear Hunt for the same historical target;
- personal damage/rank/attendance/rally trends;
- Alliance damage/attendance/rally trends.

The experience must distinguish **zero** from **not recorded**. Missing attendance, rally or result evidence must never be silently converted to zero.

## Ownership

Bear Hunt Debrief composes existing owners:

| Fact / behavior | Owner |
| --- | --- |
| Event type, occurrence, time and target | `Operations/Events` |
| Governor Event context and attendance | `Operations/Participation` |
| Rally groups, roles and participation status | `Operations/Rallies` |
| Accepted Bear Hunt reports, Governor damage/rank and result projection | `Operations/Results` |
| Screenshot observations, matching/review state and unresolved rows | `Intelligence/Evidence` |
| Historical personal/organization composition and debrief projection | `ReadModels/EventAnalysis` |

`ReadModels/EventAnalysis` may compose owner Queries/value objects but owns no Bear Hunt write state. No `Contexts/BearHunt` family, duplicate result store, duplicate attendance store, duplicate rally store or second Governor-matching workflow may be created.

## Run identity

A Bear Hunt **run** is an existing Bear Hunt `EventOccurrence`. Do not create `bear_hunt_runs`.

The debrief route is occurrence-scoped. Historical run selection must use Bear Hunt Event type plus the same historical Event target, not whichever Alliance the Governor currently belongs to.

## Result semantics

`Operations/Results` remains authoritative for accepted Bear Hunt result meaning.

The debrief uses the existing report-ledger/result projection semantics, including baseline preservation, report removal and idempotent recomputation. It must not sum OCR output independently or infer committed result truth from Evidence review rows.

Required run result fields:

- accepted report count;
- total recorded damage;
- Governor rows with current Player reference/name where authorized;
- Governor damage;
- Governor rank;
- number of accepted report contributions per Governor where useful for completeness/explanation;
- recorded result time/completeness state.

## Attendance semantics

Attendance comes from `Operations/Participation` and preserves the owner statuses `present`, `absent`, `excused`, and `unknown`.

A Governor having damage does not imply attendance, and a missing result does not imply absence.

The run summary exposes attendance totals and an attendance rate only when a meaningful denominator is available. Governor rows expose attendance status independently from damage.

## Rally semantics

Rally counts come only from authoritative `Operations/Rallies` participation evidence.

For debrief metrics:

- `role=lead` with `status=participated` counts as a rally led;
- `role=joiner` with `status=participated` counts as a rally joined;
- only `participated` is counted as actual rally participation;
- `assigned`, `confirmed`, `declined`, `absent`, and `removed` are not counted as completed rallies;
- standby participation is not silently reclassified as lead/joiner.

When rally participation was not recorded for a run, the UX shows **Not recorded** rather than `0`. The Alliance summary uses **Recorded rallies** language unless completeness is explicitly known.

## Unmatched-Governor review

Unresolved Governor observations remain owned by `Intelligence/Evidence`.

The debrief may show a bounded review queue containing the minimum information needed to explain pending work, such as observed name, visible damage/rank, confidence/review state and Evidence/review identity. It must not implement a second matching workflow.

An Evidence item is an unmatched-Governor item only while Governor row matching still remains unresolved. If a saved Evidence review has already resolved every included row and intentionally excluded any non-result rows, the Debrief must not list those extracted rows as unmatched merely because the Evidence lifecycle remains `needs_review` for a different reason such as semantic-duplicate resolution.

Queue bounds are applied **after** Intelligence/Evidence filters items whose latest extraction already has a saved review. Reviewed semantic-duplicate follow-up must not consume the bounded unmatched queue or starve an older genuinely unmatched screenshot from Debrief review work.

The action is **Review imported report**, linking to the existing Screenshot Intake review surface. Resolution, exclusion, approval and commit continue through Evidence owner Actions and the existing idempotent Evidence → Results commit handshake.

Unauthorized Evidence, raw OCR/provider payloads and cross-Alliance duplicate information must never be exposed by the debrief.

## Previous-run comparison

The comparison run is the immediately preceding **completed Bear Hunt occurrence for the same historical Event target**.

It must not select:

- a different Event type;
- another Alliance/Kingdom target;
- an occurrence after the current run;
- an occurrence merely because it is the previous database row.

Comparison fields where available:

### Alliance

- total damage and absolute change;
- percent change when the previous value is greater than zero;
- Governors with recorded result data;
- attendance count/rate;
- recorded rallies.

### Active Governor

- damage and absolute/percent change;
- rank and rank movement;
- attendance;
- recorded rallies.

A previous value of zero must not produce misleading infinite/undefined percentage text; return an explicit comparison state instead.

## Personal trends

Reuse `ReadModels/EventAnalysis` history and owner result/participation/rally evidence. Default to a bounded recent window and enforce a server-side maximum.

Each trend point may expose:

- occurrence ID/date;
- damage when recorded;
- rank when recorded;
- attendance status when recorded;
- recorded rally count when available.

Derived presentation values may include latest value, rolling average, best damage, best rank and previous-run change. Do not persist a duplicate analytics aggregate unless profiling later proves a need and architecture docs are updated first.

## Alliance trends

Reuse organization history for the same historical target. A trend point may expose:

- occurrence ID/date;
- total recorded damage;
- number of Governors with recorded result data;
- attendance total/rate where meaningful;
- recorded rally count where available.

The API/view model must retain availability/completeness flags so the frontend can distinguish `0` from `not recorded`.

## Authorization

Authorization is required before composing private debrief data and must protect each source boundary.

An authorized viewer must not learn another tenant/Alliance's:

- Bear Hunt damage totals;
- Governor identities/results;
- attendance;
- rally participation;
- unresolved Evidence;
- historical trend/comparison data.

Historical comparisons use frozen/historical Event target context rather than assuming current membership represents past authority.

## Idempotency and mutations

The debrief is primarily read-only and introduces no artificial idempotency mechanism for GET/read composition.

Any mutation reached from the debrief continues through the existing owner Action and owner idempotency/concurrency boundary:

- Evidence review/commit → `Intelligence/Evidence` and existing Results handshake;
- accepted report correction/removal → `Operations/Results`;
- attendance correction → `Operations/Participation`;
- rally participation correction → `Operations/Rallies`.

No debrief controller or Vue component writes owner models directly.

## Audit and observability

Routine debrief reads do not create noisy domain audit history.

Owner mutations retain their existing audit/outbox contracts. Debrief diagnostics must be privacy-safe and may include:

- debrief query/render latency;
- number of history points returned;
- incomplete-data state counts;
- unresolved Governor count;
- previous-run availability;
- trend-query latency and owner-query failure category.

Diagnostics must not contain Governor names, screenshot content, OCR text, evidence hashes or unauthorized tenant details.

## UX contract

Primary route: `/events/{occurrence}/debrief` for applicable Bear Hunt occurrences.

The dedicated debrief page contains:

1. **Run summary** — date/status, total damage, Governors with results, attendance and recorded rallies.
2. **Your Hunt** — active Governor damage/rank/attendance/rallies and previous-run movement.
3. **Governor leaderboard** — damage, rank, attendance and recorded rally count with responsive mobile cards.
4. **Needs review** — only when unresolved Evidence exists; links into Screenshot Intake review.
5. **Previous Hunt** — concise Alliance and personal deltas with missing/zero-safe semantics.
6. **Trends** — personal and Alliance trend visuals with equivalent textual summaries.
7. **Run history** — recent Bear Hunt runs newest-first with bounded navigation.

The occurrence page exposes **View debrief** only when the occurrence is a supported Bear Hunt target and the viewer is authorized.

### Required UX states

Visual/behavior coverage must include:

- no results yet;
- attendance only;
- result reports only;
- complete run;
- rally data not recorded;
- recorded zero rallies;
- unresolved Governors;
- Evidence awaiting review;
- no previous run;
- one historical run;
- historical run missing rally evidence;
- active Governor has no personal result;
- report removed and result recomputed;
- large Governor list;
- long Governor names;
- long localized strings;
- mobile and desktop layouts.

## Accessibility

The debrief must provide:

- semantic heading hierarchy;
- keyboard-operable navigation/actions;
- visible focus;
- meaningful table/card semantics;
- non-color-only trend direction and status;
- textual equivalents for charts;
- localized number/date formatting;
- sufficient mobile tap targets;
- reduced-motion-safe presentation;
- accessible action receipts for mutations reached from the page.

A trend arrow alone is not an accessible result. For example, expose text equivalent to `Damage increased 12.3% from the previous Bear Hunt`.

## Localization and KingShot language

Use established KingShot terms: **Bear Hunt**, **Governor**, **Alliance**, **Damage**, **Rank**, **Rally**, **Attendance**, **Previous Hunt**, and **Needs review**.

Do not introduce generic enterprise-dashboard language such as employee/member-performance/session when a KingShot term exists.

All supported application locales must contain native keys for the changed debrief surface and pass localization/type/build enforcement.

## Performance and bounds

- History/trend queries are bounded by default and enforce a hard server-side maximum.
- Unmatched-Evidence filtering occurs before its bounded queue limit so reviewed duplicate follow-up cannot starve unresolved Governor work.
- Avoid per-Governor N+1 queries.
- Composition should batch owner reads by occurrence/player IDs.
- Older/incomplete runs must remain usable without requiring backfill.

## Test contract

Required automated coverage includes:

- total damage and Governor rank/damage projection;
- report replay/removal/baseline behavior reflected in debrief reads;
- all attendance statuses;
- participated lead/joiner rally counting and exclusion of non-participated statuses;
- missing rally evidence vs recorded zero;
- unresolved/resolved/excluded Evidence behavior, including duplicate-blocked Evidence that is still `needs_review` but has no unmatched Governor rows;
- bounded unmatched-Evidence non-starvation when reviewed duplicate-follow-up items are newer than unresolved work;
- tenant-safe Evidence and historical authorization;
- correct previous-run selection;
- zero/missing comparison semantics;
- bounded personal/Alliance history ordering;
- historical target/context behavior;
- active Governor with/without result;
- no N+1 regression at realistic Governor count;
- route/controller authorization and Inertia contract;
- accessibility and deterministic visual regression.

## Delivery queue

All phases are complete. Any defect or material change that invalidates an exit condition reopens the affected phase and must restore the same evidence before closeout.

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 1 | Complete | Product contract and ownership | `/docs/product` defines the complete debrief and existing-owner boundaries; catalogue/ledger/journeys reflect implemented delivery. |
| 2 | Complete | Results and attendance read contracts | Authoritative total/Governor result and attendance summaries are batched, bounded where applicable and behavior-tested. |
| 3 | Complete | Rally and unresolved-Evidence read contracts | Actual rally participation and unmatched Governor review summaries are owner-query based, availability-aware, non-starving under the queue bound and authorization-tested. |
| 4 | Complete | Debrief composition and history | EventAnalysis composes current run, previous run, personal trends, Alliance trends and run history without becoming a writer. |
| 5 | Complete | Authorized HTTP/Inertia surface | Bear Hunt occurrence entry and dedicated debrief route/page enforce current/historical target authority. |
| 6 | Complete | Responsive UX, accessibility and localization | Complete/missing/review/mobile/desktop states and supported locales are implemented with accessible trend/chart equivalents. |
| 7 | Complete | Audit/observability and mutation integration | Owner mutations retain audit/idempotency; privacy-safe debrief diagnostics and recovery links are complete. |
| 8 | Complete | Behavior, architecture and visual regression | Backend/frontend/authorization/architecture/query-performance and deterministic visual coverage is green. |
| 9 | Complete | Final contract audit and release closeout | Spec→code, code→spec, UX→backend, data-ownership and authorization audits found no gap; all applicable repository gates passed on immutable implementation head `fd821e470ef19f51bfff14499c3f417f3cd3eeff`. |

The Bear Hunt Debrief delivery queue is closed: every phase is Complete and no known Bear Hunt Debrief product feature is deferred. Final closeout documentation is status-only; the closeout head must repeat the applicable repository gates before merge. Any later defect or material change that invalidates an exit condition is a regression that reopens the affected phase.

## Cross-phase invariants

1. No `BearHunt` bounded context is introduced.
2. The run identity is the existing Bear Hunt Event occurrence.
3. Results truth is Operations-owned; Evidence is provenance/review, not committed result truth.
4. Missing data is never silently converted to zero.
5. Rally counts mean recorded actual participation, not assignment/confirmation.
6. Historical comparison uses the same historical Event target.
7. Unmatched Governor resolution reuses Screenshot Intake rather than duplicating identity matching.
8. Debrief composition is read-only; mutations route through owner Actions.
9. Public owner contracts exchange scalar IDs/value objects/read DTOs, not foreign Eloquent models.
10. No phase is Complete unless UX, backend, authorization, tests, observability, docs and applicable visual/release evidence agree.

## Definition of done

Delivery remains complete only while every item below is true:

- run history is available and bounded;
- total recorded Alliance damage is authoritative;
- Governor damage/rank are authoritative;
- attendance is independent from result inference;
- rally count is available only from recorded participation evidence and distinguishes unavailable from zero;
- unresolved Governor review links to the existing Evidence review workflow;
- previous-run comparison selects the correct historical Bear Hunt target;
- personal and Alliance trends preserve missing-data semantics;
- authorization prevents cross-tenant/current-vs-historical leakage;
- mutations reached from the debrief remain owner-authorized/idempotent/audited;
- diagnostics are useful and privacy-safe;
- desktop/mobile UX, accessibility, localization and visual regression are complete;
- PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, accessibility/visual regression, CodeQL, dependency review, production image/container scanning, staging, clean-database install and backup/restore checks are green where applicable;
- final spec→code, code→spec, UX→backend, authorization, architecture and data-ownership audits find no known planned, partial, placeholder, TODO or undocumented behavior.
