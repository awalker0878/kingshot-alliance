# KINGDOMS-002 Slice D validation

**Slice:** D / `K2-P5` — Explicit completion + roster handoff  
**Validated runtime SHA:** `3e89aab32bc4f824ad65d628dbcbbaab13da1f82`  
**Stack dependency:** validated Slice C2 / PR #40  
**Status:** Validated; `KINGDOMS-002` remains In progress

## Validated capability

Slice D adds explicit per-participant real-world completion and roster handoff to transfer planning without implementing inferred eligibility, transfer-resource/pass optimization, bulk completion, automated transfer execution, or external game integration.

The validated runtime provides:

- one alliance/plan/participant-scoped `TransferCompletion` per participant as the durable idempotency boundary;
- completion only while the transfer plan is `locked`;
- explicit `confirmed` readiness required before completion, while keeping `confirmed` itself planning-only;
- home-Kingdom drift rejection before roster handoff;
- incoming completion through the accepted roster create/update action;
- optional explicit same-alliance existing-roster selection for incoming participants, never display-name-only matching;
- stable game-player identity agreement when an incoming participant with a stable ID links an existing roster result;
- preservation of accepted existing roster name, role, state, joined date, source, manager notes, and existing membership linkage during explicit link handoff;
- outgoing completion through accepted `MarkRosterEntryLeft` behavior;
- staying completion as an explicit outcome with no roster lifecycle mutation;
- preservation of neutral player identity and existing snapshot history;
- no fabricated `PlayerSnapshot` during completion;
- participant-row serialization and existing-completion lookup before delegated roster side effects, making retries idempotent;
- internal `kingdoms.transfer_participant_completed` audit/outbox evidence;
- ordinary member visibility limited to safe completion time, with completion actor and roster-result provenance manager-only;
- a dedicated manager Completion workspace with separate per-participant real-world confirmation; and
- a Locked → Closed invariant requiring every non-withdrawn participant to have explicit completion first.

There is intentionally no bulk “complete all ready/confirmed players” action or route.

## Security and integrity evidence

Every completion re-resolves the active Alliance, transfer plan, participant, and any explicitly selected roster result beneath the same tenant boundary and requires:

- `kingdoms.manage`;
- recent password confirmation;
- a `locked` transfer plan;
- explicitly `confirmed` participant readiness;
- a non-withdrawn participant; and
- unchanged captured home-Kingdom context.

Cross-alliance submitted roster identifiers fail closed. Coordinator assignment remains workflow responsibility only and grants no completion authority.

Incoming completion does not move the planning/source neutral `Player` into the home Kingdom. Accepted roster resolution produces the roster result, which is referenced by the completion record. Completion does not create or rewrite player snapshots.

Private participant/group notes and blocker text are not copied into completion event metadata. Ordinary member transfer payloads do not expose completion actor, completion record IDs, selected/result roster IDs, or richer handoff provenance.

## Direction-specific handoff evidence

### Incoming

Protected acceptance coverage verifies both supported paths:

1. create a new accepted home-Kingdom roster result after actual arrival; and
2. explicitly select an existing active/tracked same-alliance roster result.

Existing roster selection is explicit. Stable game-player identifiers must agree when available, and mismatches roll back without completion. Accepted private roster fields are preserved on explicit linking.

### Outgoing

Completion re-resolves the participant's captured same-alliance roster binding and delegates to accepted `MarkRosterEntryLeft`. Retry coverage verifies the roster lifecycle event and completion event are not duplicated.

### Staying

Completion records the transfer outcome while leaving the accepted roster lifecycle state unchanged.

## Protected validation

The validated runtime SHA passed the protected repository gate end-to-end:

- Dependency Review run `31326515529`: success;
- CodeQL run `31326515535`: success;
- CI run `31326515555`: success;
- frontend ESLint, pinned Prettier, Vue/TypeScript checks and production build: success;
- PostgreSQL migrations, including `2026_08_09_130000_create_transfer_completions.php`: success;
- Pint: 437 files passed;
- PHPStan/Larastan: 314 files, 0 errors;
- ParaTest/PHPUnit: 293 tests, 3246 assertions, success;
- immutable production image build: success;
- ephemeral staging deployment: success;
- backup/restore demonstration: success; and
- image vulnerability scan: success.

The Slice D feature/architecture validation includes explicit coverage for:

- incoming accepted roster creation with no fabricated snapshot;
- explicit incoming existing-roster linking and accepted/private-field preservation;
- outgoing delegated mark-left and idempotent retry/event counts;
- staying completion without roster lifecycle mutation;
- `confirmed` readiness and `locked` plan prerequisites;
- recent password confirmation;
- Locked → Closed rejection until active participants are completed, with withdrawn participants retained as historical exceptions;
- cross-tenant roster-ID tampering;
- home-Kingdom drift;
- stable identity mismatch rollback;
- member-safe versus manager-only completion presentation;
- migration rollback/reapply dependency order; and
- architecture guards excluding eligibility, transfer-pass/resource, snapshot-power, bulk-completion, and automatic-transfer placeholders.

## Deferred boundary

Slice D does **not** implement:

- transfer passes/tickets/resources or eligibility rules;
- inferred/automatic readiness or eligibility;
- automated destination ranking or stay/leave recommendations;
- bulk completion;
- automated in-game transfer execution;
- marketplace/public advertising;
- diplomacy/NAP intelligence;
- cross-alliance transfer visibility;
- scraping/OCR/bots/undocumented game APIs;
- AI/punitive player scoring; or
- public Kingdoms API/webhook contracts.

`K2-P5` is validated. Whole-increment hardening and acceptance remain `K2-P6`; `KINGDOMS-002` is not Accepted until those remaining gates pass.
