# Bear Hunt Debrief operations

Status: Current — 2026-08-23

Bear Hunt Debrief is a synchronous read surface over existing owner data. It has no queue, scheduler, retry worker, retention policy or writable recovery state of its own.

## Observability

Every successful Debrief HTTP composition emits the structured log event `bear_hunt.debrief.viewed` with privacy-safe dimensions:

- `occurrence_id` and `alliance_id`;
- Results availability and Governor count;
- attendance-data availability;
- Rally-data availability;
- unresolved Governor count;
- bounded history count;
- whether the caller may review Evidence.

The event intentionally excludes Governor names, OCR text, Evidence contents and damage values. Authentication/authorization failures are handled by the existing HTTP exception/logging path and do not emit a successful-view event.

Mutations feeding the Debrief remain observable through their owner-context audit/outbox/retry facilities. Do not add a Debrief audit row for a read simply to duplicate those records.

## Expected degraded states

These are valid product states, not operational errors:

- no Results recorded yet;
- no Attendance recorded yet;
- no Rally outcomes recorded yet;
- recorded Rally outcomes with zero participation;
- no previous completed same-Alliance Bear Hunt;
- unresolved Governor Evidence awaiting manager review;
- Evidence that is still `needs_review` for duplicate resolution after all Governor rows were already resolved/excluded; this does **not** appear as unmatched-Governor work;
- a historical run with only some owner data available.

The UI must label these states explicitly and never substitute zero for missing evidence.

## Troubleshooting

When a Debrief looks incomplete, verify the owner in this order:

1. `Operations/Events`: occurrence is the expected Alliance Bear Hunt and the historical target is correct.
2. `Operations/Results`: Event Player result projection and accepted Bear Hunt report ledger contain the expected result facts.
3. `Operations/Participation`: attendance rows exist for the occurrence.
4. `Operations/Rallies`: assignments have a non-null recorded outcome before Rally participation is considered available.
5. `Intelligence/Evidence`: unresolved screenshots use `needs_review`, are scoped to the same Alliance/occurrence and have a latest completed extraction that does not already have a saved review. If the latest extraction has a review with duplicate-blocked status, Governor matching is already complete and Debrief correctly omits it from unmatched-Governor work.
6. `EventAnalysis`: the occurrence is inside the bounded history window and the request has `event.alliance.view` authority.

Do not repair Debrief output by writing directly to read-model state; there is no such state. Correct or replay data through the owning capability.

## Recovery

- Screenshot commit interruption/retry uses the existing Evidence-to-Results idempotent commit receipt/report ledger.
- Result correction or accepted-report removal is recovered in Results and is reflected on the next Debrief read.
- Attendance and Rally corrections are performed through their existing owner write flows and are reflected immediately on the next read.
- Unmatched Governor recovery is performed in Screenshot Intake; the Debrief review queue disappears when the latest extraction has a saved review or Evidence otherwise leaves the unresolved matching state.
- Semantic-duplicate recovery remains Screenshot Intake/Evidence work and is not mislabeled as Governor matching in Debrief.

No Debrief cache is persisted, so there is no cache invalidation or replay procedure specific to this feature.

## Bounds

The Debrief history window is intentionally bounded. Current implementation returns at most 12 run navigation/trend points and the low-level history query rejects unbounded expansion beyond its defensive maximum. Evidence review results are independently bounded by Intelligence/Evidence and batch-loaded by Evidence/review/extraction/field identifiers rather than fetched per Evidence row.
