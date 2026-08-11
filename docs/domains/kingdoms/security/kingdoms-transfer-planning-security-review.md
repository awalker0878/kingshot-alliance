# KINGDOMS-002 whole-increment security review

[← Security index](README.md)

**Scope:** `KINGDOMS-002` transfer planning  
**Decision:** **Accepted**  
**Validated implementation SHA:** `64189559c66e15dc56ec31f9b340284c89c30e6c`

## Security decision

The complete transfer-planning increment passed the repository security/integrity acceptance gate. The accepted model keeps transfer coordination tenant-owned, permission-driven, manual/explicit and separate from neutral Kingdom/player reference identity.

## Tenant and object-ID boundaries

- `TransferPlan`, participant, group, readiness/blocker and completion data are alliance-owned.
- Global `Kingdom` and `KingdomPlayer` references never authorize access to an alliance transfer plan.
- Submitted plan, participant, group, roster and membership identifiers are re-resolved beneath the active Alliance and plan boundary.
- Cross-alliance object-ID substitution fails closed, including when two alliances share the same Kingdom reference.
- Destination Kingdom references do not create cross-alliance visibility or mutation rights.

## Authorization and coordinator confusion

Ordinary transfer visibility uses `alliance.view`; privileged mutations use `kingdoms.manage` plus the accepted recent-password-confirmation controls.

Coordinator assignment is operational metadata only. A coordinator who lacks `kingdoms.manage` cannot use the assignment to mutate groups, readiness, blockers or completion state. Custom-role permission union semantics remain authoritative; no controller role-name shortcut is introduced.

## Identity ambiguity

Application identity, alliance membership, neutral game identity and transfer participation remain distinct.

Incoming planning may exist before membership/roster creation. Display name alone never auto-merges a neutral player or accepted roster result. Stable game-player identity is the automatic identity key when available, and explicit existing-roster linking fails on stable-ID mismatch.

Planning an outgoing destination never moves a neutral `KingdomPlayer` to that Kingdom.

## Home-Kingdom and destination integrity

A plan captures the Alliance home Kingdom. Transfer mutations and completion revalidate that captured context. If the Alliance Kingdom changes, the workflow fails closed rather than silently retargeting incoming participants or applying stale handoff assumptions.

Outgoing/group destination compatibility is validated transactionally. Staying participants are not assigned a false transfer destination.

## Readiness and blocker safety

Readiness is manual workflow state, not inferred eligibility or player value.

- no automatic readiness/eligibility scoring exists;
- blockers require explicit manager action;
- resolving the final blocker does not silently mark a participant ready;
- readiness transition history preserves attributable state change; and
- private blocker details/manager notes remain manager-only and are excluded from generic event metadata/logging paths.

## Completion and roster integrity

Completion is an explicit per-participant action in the locked-plan phase.

- `confirmed` readiness alone never mutates the roster;
- incoming completion delegates to accepted roster create/link/update behavior;
- outgoing completion delegates to accepted mark-left behavior;
- staying completion performs no roster lifecycle mutation;
- one completion record per participant is the durable idempotency boundary;
- participant locking and existing-completion lookup serialize retries before delegated side effects;
- completion does not fabricate `PlayerSnapshot` observations; and
- a plan cannot close while a non-withdrawn participant lacks explicit completion.

## Privacy boundary

Ordinary member presentation exposes approved direction/group/readiness/completion status only. Manager-only data includes private notes, blocker details, coordinator membership identifiers where privileged, completion actor and roster-result provenance.

Shared player/Kingdom references do not turn private transfer observations into global data.

## Integration boundary

No public Transfer/Kingdoms API scope or route is introduced.

`alliance.kingdom_updated` and `kingdoms.*`, including `kingdoms.transfer_*`, remain internal transactional-outbox events. Generic outbound webhook fan-out rejects these events even for wildcard subscriptions. Any future external transfer contract requires separate approval and schema/security review.

## Abuse and scope-boundary review

The accepted runtime contains no:

- transfer marketplace/public advertising;
- automatic stay/leave recommendation;
- destination or player ranking;
- player-value/desirability scoring;
- inferred transfer eligibility/readiness;
- transfer-pass/ticket/resource optimization;
- bulk completion;
- automated in-game transfer execution;
- scraping/OCR/bot/undocumented game-data ingestion;
- cross-alliance transfer intelligence; or
- AI/punitive player recommendation workflow.

Architecture/schema guards prevent these deferred concepts from appearing as dormant runtime placeholders.

## Acceptance evidence

The exact implementation SHA passed Dependency Review, CodeQL and the full repository CI gate, including dependency audits, migrations, static analysis/tests, immutable-image build, staging, backup/restore and image scanning.

See the [KINGDOMS-002 exit report](../product/kingdoms-transfer-planning-exit-report.md) for exact run IDs, counts and whole-increment evidence.

Repository/product acceptance does not authorize real production cutover; production approval remains a separate decision.
