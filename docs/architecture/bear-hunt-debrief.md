# Bear Hunt Debrief architecture

Status: Current implementation contract — 2026-08-23

Bear Hunt Debrief is a composed read experience. It is not a bounded context and it does not own persistence.

## Ownership

| Concern | Owner | Debrief use |
| --- | --- | --- |
| Event/run identity, target and occurrence status | `Operations/Events` | Selects the Bear Hunt `EventOccurrence` and historical run set. |
| Governor damage, rank and accepted battle-report truth | `Operations/Results` | Reads the projected Event result plus accepted Bear Hunt report provenance. |
| Attendance | `Operations/Participation` | Reads recorded attendance without inferring missing rows. |
| Rally participation | `Operations/Rallies` | Reads only explicitly recorded assignment outcomes; only `participated` counts participation. |
| Screenshot provenance, extraction and unresolved Governor review | `Intelligence/Evidence` | Reads a manager-authorized unresolved review summary and links back to Screenshot Intake. |
| Cross-owner history/comparison/trends | `app/ReadModels/EventAnalysis` | Composes already-owned facts into a bounded debrief payload. |

`EventAnalysis` never writes owner tables. No `BearHunt` application/domain namespace, schema, repository, job family or aggregate is introduced.

## Read flow

1. `BearHuntDebriefController` resolves the active Player and requested occurrence through the existing Events calendar boundary.
2. The occurrence must be an Alliance-scoped `bear-hunt` Event.
3. `BearHuntDebriefQuery` reacquires `event.alliance.view` authority before historical Alliance reads.
4. Owner queries return current Results, Participation and Rally facts.
5. EventAnalysis loads at most the configured recent Bear Hunt occurrence window for the same Alliance target and composes history, previous-run comparison and trend series.
6. When the caller can manage the occurrence, Intelligence/Evidence returns unresolved rows through its own authorization boundary. Non-managers receive no unresolved Evidence payload.
7. The controller emits one privacy-safe structured read event and returns the Inertia view model.

## Data semantics

Missing owner data remains `null`/unavailable and is never converted to zero. Recorded Rally rows make Rally evidence available even when the resulting `participated` count is zero. Previous-run selection skips the current occurrence and chooses the immediately preceding completed Bear Hunt for the same Alliance target.

Historical Results remain owned by Results. The read model never recomputes accepted screenshot rows as domain truth; it consumes the owner projection so replay, correction, report removal and preserved baseline behavior stay centralized in Results.

## Authorization

The route is authenticated and requires an active Player. Current-occurrence visibility is enforced by the existing Events read boundary. The composition query separately authorizes Alliance Event view before reading history. Evidence review remains manager-only and is authorized again inside Intelligence/Evidence.

No caller-supplied Alliance ID, Player ID or Evidence ID is trusted as authority.

## Dependency direction

Allowed dependency direction for this capability is:

`HTTP -> EventAnalysis read composition -> owner read/query contracts`

Owner contexts do not depend on EventAnalysis or the Debrief page. Cross-context write coordination is unchanged from Screenshot Intake and existing Operations actions.

## Mutation and idempotency boundary

Debrief is read-only. It intentionally has no idempotency store or mutation endpoint. Mutations visible in the Debrief continue through their existing owners:

- accepted Screenshot Intake report commit/retry -> Evidence coordination + Results idempotency/report ledger;
- attendance changes -> Participation owner actions;
- Rally assignment/outcome changes -> Rallies owner actions;
- Evidence matching/review -> Intelligence/Evidence owner actions.

Those mutation paths retain their existing authority revalidation, audit/outbox behavior and idempotency guarantees where applicable.
