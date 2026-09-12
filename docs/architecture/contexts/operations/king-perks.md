# Operations — KingPerks

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations/KingPerks`

KingPerks owns Kingdom of Power appointment and King Skill planning/scheduling against operational Event/phase timing.

## Core rules

- appointment occupancy is 30 minutes;
- Player cooldown is 60 minutes and is anchored after the appointment end;
- position cancellation blocks that position for 30 minutes;
- the scheduler validates occupancy/cooldown against the actual planned time rather than merely storing an arbitrary slot;
- source Event/phase timing remains Operations-owned;
- Communications owns generic delivery attempts, while reminder meaning/timing stays Operations-owned.

## Authority

KingPerks uses Operations authority derived from the active Player and concrete Kingdom scope. Platform Administrator is not a game-domain bypass.

## Lifecycle concepts

Planning supports plan creation/publication, appointment assignment/reassignment/confirmation/completion/no-show and skill planning/scheduling/activation. Durable events/outbox messages represent completed persisted transitions rather than becoming a second source of truth.

## Reminder traversal

KingPerks owns reminder timing, candidate discovery and resumable audience traversal; Communications owns resulting logical notifications and provider delivery. The six existing reminder kinds share authoritative status/lead-time predicates in KingPerkReminderKind. Due-source pages and manager audiences are bounded before materialization. At each write, actual current Kingdom/Player authority and source state/timing are rechecked; discovery DTOs and cursors never authorize a send.

One private source/kind cursor advances atomically with each recipient's notification/outbox transaction, with version and replacement-identity fencing. The existing scheduler command uses an attempted-work budget, including denials, replays and empty-source visits, and reports queue counts separately. Source rotation and cyclic audience traversal preserve finite-set progress without silently discarding later recipients. [ADR-0048](../../adr/0048-bounded-king-perk-reminder-traversal.md) defines the bounded coordination and deliberate orphan-expiry policy; [background processing](../../../operations/background-processing.md) owns commands and operational limits.
