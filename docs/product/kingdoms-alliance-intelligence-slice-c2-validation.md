# KINGDOMS-003 Slice C2 validation

[← KINGDOMS-003 implementation plan](kingdoms-alliance-intelligence-implementation-plan.md)

**Scope:** `KINGDOMS-003` Slice C2 / `K3-P4`  
**Status:** Candidate — protected validation pending

This record becomes validation evidence only after one exact runtime implementation SHA passes the complete protected repository gate.

## Candidate contract

Slice C2 adds a minimal manager-private diplomacy contact directory on top of the fully green Slice C1 diplomacy lifecycle.

Required runtime evidence includes:

- tenant-owned contacts scoped to Alliance + tracked game-side alliance + neutral reference;
- display name, game-side role/title, approved handle-based channel, handle, active/inactive state, last-verified time and manager-private notes only;
- no `KingdomPlayer`, `User`, membership, role, or permission linkage;
- duplicate names/handles remain distinct and never auto-merge or auto-link identity;
- manager-only contact workspace and contact detail;
- `kingdoms.manage` plus recent password confirmation for create/update/deactivate;
- active/current-Kingdom context revalidation for privileged mutations;
- exact active-update retry idempotency;
- inactive-history preservation and idempotent deactivation with no destructive delete route;
- contact changes never infer or transition diplomacy;
- contact assignment grants no permission and creates no platform account/membership/player identity;
- member payloads contain no contact detail or internal contact IDs;
- private contact text excluded from audit/outbox payloads;
- internal-only `kingdoms.diplomacy_contact_saved` and `kingdoms.diplomacy_contact_deactivated` events;
- PostgreSQL migration rollback/reapply through K3/K2/K1;
- accessible manager controls and bounded contact listing; and
- explicit absence of scoring/ranking/recommendation, ingestion, public API/webhook, automated negotiation, and automatic transfer behavior.

## Required protected checks

The exact validated runtime SHA must pass:

- Dependency Review;
- CodeQL;
- frontend dependency audit, ESLint, pinned Prettier, Vue/TypeScript and production build;
- Composer validation and dependency audit;
- PostgreSQL migrations;
- Pint;
- PHPStan/Larastan;
- full ParaTest/PHPUnit suite;
- immutable production image build;
- ephemeral staging deployment;
- backup/restore demonstration;
- image vulnerability scan; and
- cleanup.

Do not use diagnostic/formatter heads as the validation anchor. A later documentation-only status commit may sit above the validated runtime SHA without replacing it.

`KINGDOMS-003` remains **In progress** after Slice C2 validation; descriptive intelligence remains `K3-P5`, and whole-increment acceptance remains `K3-P6`.
