# Kingdoms foundation security review

[← Security documentation](README.md)

**Scope:** `KINGDOMS-001` Slice A / `K1-P1`  
**Status:** Current implementation review  
**Runtime contract:** [Kingdoms](../README.md)

This review covers the first-class Kingdom reference and Alliance→Kingdom association introduced by Slice A. It does not claim review of later game-player, roster, snapshot, intelligence or CSV capabilities; those require additional review in their owning phases.

## Assets and trust boundaries

### Global reference data

`Kingdom` is global neutral reference data identified by a canonical numeric kingdom number. Multiple alliances may reference the same Kingdom.

The Kingdom record itself is not a tenant. It must never be used to authorize access to alliance-owned data.

### Alliance-owned relationship

An Alliance's selected Kingdom is part of the alliance aggregate's configuration. Changing it is an alliance-scoped privileged action.

The active-alliance context and normal alliance authorization remain the trust boundary. Sharing a Kingdom does not grant one alliance visibility or mutation rights over another alliance.

## Threats and controls

| Threat / abuse case | Control | Verification |
| --- | --- | --- |
| User changes another alliance's Kingdom by submitting an identifier | Mutation never accepts an Alliance ID from the form; it operates on the active `AllianceContext`. `AllianceAuthorization` checks `alliance.manage` for that active Alliance. | Feature/tenant authorization tests. |
| Member without configuration authority changes Kingdom | Read and mutation authorization use `alliance.manage`; mutation also requires recent password confirmation. | Feature tests for unauthorized member and password-confirmation redirect. |
| Shared Kingdom becomes an accidental cross-tenant lookup key | Kingdom is documented and modeled as global reference only; alliance data continues to be keyed/scoped by Alliance. No roster or tenant-owned Kingdom data is introduced in Slice A. | Domain-boundary tests/review and current architecture contract. |
| Legacy Kingdom values are silently lost during migration | Migration is fail-closed for non-empty values that cannot be normalized safely. The legacy column is dropped only after successful backfill. | Migration/schema validation and staging migration. |
| Dual persistence models drift after migration | The legacy `alliances.kingdom` column is removed and no compatibility accessor is introduced. Existing presentation/API `kingdom` fields derive from the relation. | Schema contract test and runtime regression tests. |
| Alliance Kingdom is changed independently of game identity | There is no Alliance Kingdom mutation endpoint; Alliance Kingdom is established from the creating R5 Player and membership/roster/transfer guards preserve compatibility. | Creation/settings/transfer feature tests. |
| Alliance/Player Kingdom drift is introduced | Active membership and active/tracked roster constraints reject incompatible Player Kingdom changes; R5 transfers require leadership handoff first. | Membership/roster/transfer tests and schema guards. |
| Content manager changes Kingdom through public-profile editing | Kingdom mutation is removed from the Content action, controller validation and Content management UI. | Content regression tests/code review. |
| API compatibility requires retaining unsafe legacy storage | `/api/v1/alliance` keeps its `kingdom` representation but derives it from the relation; no legacy persistence field remains. | API regression test/schema contract. |

## Identity assurance

Changing the Alliance→Kingdom relationship requires:

- authenticated session;
- active authenticated-session checks;
- verified email;
- active alliance context;
- effective `alliance.manage`; and
- recent password confirmation.

Slice A deliberately does not introduce `kingdoms.manage`. That permission belongs to roster mutations in Slice B; adding it before the protected capability exists would unnecessarily expand the authorization vocabulary.

## Data classification and privacy

The canonical Kingdom number is treated as low-sensitivity game-world reference data. Slice A stores no private player observations, roster notes, membership links, power history or cross-alliance intelligence in the Kingdom record.

Future `KINGDOMS-001` phases must not infer that game-observable data is automatically public. Their alliance-owned observations remain subject to the authenticated tenant boundary defined by the approved scope.

## Migration and rollback security

The migration accepts a deliberately narrow set of legacy representations and refuses malformed non-empty values. Operators must correct invalid source data rather than weakening the migration to discard it.

The rollback recreates the former string representation from canonical Kingdom numbers for controlled development/test rollback. Runtime application code does not support the old field after the forward migration.

## Residual risks and follow-on review

- Concurrent first creation of the same Kingdom relies on the database unique constraint and framework create semantics; tests/CI should surface any unresolved race handling requirement before Slice A acceptance.
- Platform-admin provisioning uses the same `CreateAlliance` domain action and therefore receives the canonical resolver boundary; its HTTP validation should remain consistent with the canonical numeric contract.
- Later `Player`, roster, snapshots, CSV import/export and comparative metrics introduce materially larger privacy/abuse surfaces and require dedicated review before their phase closes.
- No external production-infrastructure control is proven by this Slice A review. Real production launch remains governed by the existing production launch security review and approval record.
