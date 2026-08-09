# KINGDOMS-002 Slice C2 readiness security review

**Scope:** Slice C2 / `K2-P4` — manual readiness and blockers  
**Status:** Candidate implementation review

## Security objective

Readiness and blockers are alliance-private planning state. Slice C2 must make workflow state explainable and attributable without creating a new authorization path, leaking private blocker content, inferring eligibility, or accidentally performing transfer completion.

## Tenant boundary

All readiness/blocker mutations begin from the active Alliance and re-resolve:

- transfer plan under that Alliance;
- participant under that Alliance and plan; and
- blocker, when supplied, under that same Alliance, plan and participant.

A submitted participant/blocker ID from another Alliance fails closed. Sharing a Kingdom, destination, neutral `KingdomPlayer`, player name, group name, or coordinator never creates cross-alliance access.

## Authorization boundary

The manager Readiness board requires `kingdoms.manage`.

Readiness transitions, blocker creation, blocker resolution and participant withdrawal require:

- `kingdoms.manage` through normal policy/permission authorization;
- recent password confirmation through route middleware;
- mutable plan state (`draft` or `open`); and
- unchanged captured home-Kingdom context.

Coordinator assignment remains workflow metadata only. A coordinator with no `kingdoms.manage` receives no readiness/blocker mutation authority.

## Readiness integrity

Current readiness is stored on the participant for efficient board/filter reads, while material changes append `TransferReadinessTransition` history.

The state machine rejects workflow-skipping jumps. Entering `blocked` requires an active blocker. `ready`/`confirmed` reject active blockers. Leaving `blocked` for an active state requires blockers to be resolved.

Resolving a blocker never mutates readiness. This prevents an attacker or accidental resolution click from silently advancing a participant.

`confirmed` is not completion authorization and invokes no roster mutation. K2-P5 remains a separate explicit action boundary.

`withdrawn` is terminal. The existing participant withdrawal endpoint delegates through readiness transition logic so terminal timestamps/state/history do not diverge.

## Blocker privacy

Blocker `summary` and `details` are manager-private tenant data.

Ordinary member transfer payloads expose only current readiness. They exclude:

- blocker IDs;
- blocker summary/details;
- blocker creator/resolver identity;
- readiness transition history/actors; and
- existing manager-only notes/private IDs.

The dedicated Readiness board is protected by `kingdoms.manage`.

Audit/outbox events contain safe scoped metadata only. Blocker summary/details are not copied into `audit_events.metadata` or `outbox_messages.payload`. Tests use unique secret blocker text and assert absence from both durable payload stores.

Operational diagnostics must not log blocker summary/details or manager notes. Safe diagnostics may use object IDs, state names and invariant failure categories.

## Object-ID tampering

C2 tests submit foreign participant and blocker IDs across two Alliances. Scoped query resolution must return not found/fail closed without mutating either tenant.

IDs supplied by the browser are never trusted as evidence of tenant ownership.

## Plan/home-Kingdom staleness

Readiness and blocker mutations are blocked after plan lock/close/cancel and when the Alliance's current Kingdom no longer matches the plan's captured `home_kingdom_id`.

The recovery path is explicit plan reconciliation/cancellation, not silent readiness migration to a different Kingdom context.

## History and deletion behavior

Resolved blockers and readiness transitions remain historical records. Actor foreign keys are nullable so user deletion does not delete historical tenant workflow state.

Existing pre-C2 withdrawn participants are normalized to current readiness `withdrawn` during migration without fabricating transition actors/history.

## Abuse and coercion boundary

Readiness states are planning workflow labels, not player-value scores. C2 contains no automatic readiness calculation, eligibility inference, ranking, recommendation, power/spending evaluation, transfer-pass/resource optimization, or automated stay/leave decision.

The UI describes readiness as manually maintained and explicitly distinguishes `confirmed` from actual roster completion.

## External exposure

New `kingdoms.transfer_*` readiness/blocker events are internal outbox events only. The existing Integrations boundary excludes `kingdoms.*` from generic external webhook fan-out.

No public Kingdoms/transfer API or webhook schema is introduced.

## Verification coverage

C2 acceptance tests cover:

- allowed/invalid readiness transitions;
- blocker prerequisite for `blocked`;
- no-auto-ready after final blocker resolution;
- `confirmed` without roster mutation;
- member-safe readiness and blocker/history privacy;
- cross-alliance participant/blocker ID tampering;
- password-confirmation, plan-state and home-Kingdom drift gates;
- terminal/idempotent withdrawal with retained transition history;
- audit/outbox private-text exclusion;
- migration rollback/reapply order;
- bounded multi-participant readiness-board query shape; and
- architecture guards excluding completion/eligibility/resource placeholders.

## Remaining boundary

Slice C2 does not implement completion/handoff. K2-P5 must independently review explicit completion idempotency, accepted roster-action delegation, stale roster binding, and no fabricated snapshots before any `confirmed` participant can affect roster lifecycle.
