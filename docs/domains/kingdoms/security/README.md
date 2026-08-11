# Kingdoms security profile

[← Kingdoms domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary security boundary:** Alliance-owned management of neutral game identity/intelligence and K4 ingestion operational state, separated from tenant authorization, source secrets, and public machine exposure

## 1. Security purpose and scope

Kingdoms protects neutral game reference identity plus Alliance-owned roster/history/import/intelligence, transfer planning, tracked game-Alliance observations/diplomacy/contacts/intelligence, and the validated K4-P1 generic ingestion control plane.

`KINGDOMS-001`–`003` are accepted. `KINGDOMS-004` is in progress: P0 is Complete and Slice A runtime is validated, while no concrete source, scheduler/worker, or automated observation promotion is approved yet.

## 2. Assets and sensitive data

Neutral `Kingdom`, `KingdomPlayer`, and `KingdomAlliance` rows are reference data, not authorization. Tenant-private data includes roster/snapshot/import provenance, transfer state/blockers, tracked observations/diplomacy/private contacts/notes, and K4 subscription/batch/candidate operational state/normalized facts.

K4 does not accept source credentials/cookies/authorization headers/password/recovery material, arbitrary endpoint URLs, or canonical raw external response bodies. Candidate normalized facts remain Alliance-owned even where the neutral game identity is shared.

## 3. Actors, authentication and authorization

Member-safe reads require authenticated verified active-Alliance context plus `alliance.view`. Kingdoms management, including K4 ingestion control/rejection, requires `kingdoms.manage`; privileged mutation routes require recent password confirmation.

Game identity, adapter identity, coordinator/contact assignment, source cursor/window, or candidate state never grants authorization. Platform authority remains separate.

## 4. Tenant and privacy boundaries

Global neutral identity never shares tenant roster/history/transfer/diplomacy/contact/ingestion state. Submitted tenant-owned IDs are re-resolved beneath the active Alliance.

K4 subscriptions capture `kingdom_id`; drift preserves historical operational rows but blocks new automated work/re-activation. Manager UI exposes bounded status/provenance and does not serialize normalized candidate payload bodies/source secrets.

## 5. Trust boundaries and data flows

Current runtime boundaries include authenticated member/manager workspaces; controlled CSV parser; accepted observation/transfer/diplomacy actions; Audit/Platform outbox; and K4 manager/configuration → adapter registry → tenant subscription/batch/candidate persistence.

K4-P1 has **no external source/network trust boundary** because production adapter configuration is empty and no scheduler/worker/acquisition exists. Concrete DNS/redirect/private-address/TLS/rate/secret/source-permission controls are deferred until source approval.

## 6. Threats, abuse cases and controls

Current threats include cross-Alliance access, shared game identity treated as authority, ambiguous auto-linking, destructive history rewriting, duplicate retries, unsafe CSV, private blocker/contact leakage, automatic diplomacy/scoring, accidental public integration exposure, and K4 arbitrary-destination/secret/payload/identity/retry abuse.

Controls include stable IDs, explicit Alliance ownership/re-resolution, append history, deterministic retries, bounded validation, manager-private fields, human-only diplomacy/transfer decisions, descriptive-only intelligence, K4 code allowlisting, no URL/secret/raw-response schema, target-specific candidate key/value bounds, stable-ID quarantine, current-Kingdom checks, and no Slice A promotion path.

See [K4 Slice A security review](kingdoms-automated-ingestion-foundation-security-review.md).

## 7. Integrity, concurrency and idempotency

K1/K3 snapshots/observations remain append-oriented with exact retry/correction semantics. Transfer/diplomacy state changes remain explicit.

K4 source-window/candidate identity plus database uniqueness/row locking make exact retries deterministic; completed batch outcomes cannot be rewritten. Slice A stops before business promotion, so it cannot bypass K1/K3 history actions.

## 8. Secrets and credential handling

Current Kingdoms owns no source API credential. K4 schema/forms contain no URL/host/header/token/cookie/password/recovery-secret fields. Source secrets/raw responses must not be copied into normalized payload fields, logs, audit/outbox metadata, or support diagnostics.

A concrete adapter requires a separately approved secret owner/storage/rotation/revocation design before enablement.

## 9. Destructive operations, retention and deletion

Kingdoms favors history-preserving correction/archival. Platform orchestrates broader legal-hold/deletion/retention while Kingdoms owns domain semantics.

K4 batches/candidates are operational scaffolding, not duplicate permanent business history. Final bounded retention/pruning behavior is intentionally a `K4-P5` requirement; until then, do not treat candidate storage as a general archive or manually rewrite/delete it to force retries.

## 10. Auditability, observability and evidence

Privileged human Kingdoms mutations create attributable audit/internal outbox evidence where required. K4 internal processing records bounded IDs/state/count/hash/failure metadata; diagnostics should use tenant/Kingdom/subscription/batch/candidate IDs and timings, not secrets/raw payloads.

K4-P1 runtime candidate `5a37731374e9fa7aef591b7b1badd9cc13603e2c` is protected-green: DR `31533284318`, CodeQL `31533284195`, CI `31533284398` with 509 Pint files, PHPStan 363/363 zero errors, 407 tests / 9,466 assertions, image/staging/backup/scan success.

## 11. Residual risks and explicit non-capabilities

Human-entered facts can be inaccurate; future source facts can also be wrong/malicious. The application therefore does not infer threat/desirability, recommendations, diplomacy, transfer decisions, or identity from descriptive labels.

No real K4 source/network, scheduler/worker, automatic observation promotion, cross-Alliance sharing, public Kingdoms API/webhook, or source credential path is currently approved. Later slices must add their own targeted review before those governed behaviors become real.

## 12. Focused reviews and related documentation

### `KINGDOMS-001`

[Foundation](kingdoms-foundation-security-review.md), [Roster](kingdoms-roster-security-review.md), [Snapshots](kingdoms-snapshot-security-review.md), [Intelligence](kingdoms-intelligence-security-review.md), [CSV](kingdoms-csv-security-review.md), [whole increment](kingdoms-roster-intelligence-security-review.md).

### `KINGDOMS-002`

[Foundation](kingdoms-transfer-planning-foundation-security-review.md), [Participants](kingdoms-transfer-participant-security-review.md), [Groups](kingdoms-transfer-group-security-review.md), [Readiness](kingdoms-transfer-readiness-security-review.md), [Completion](kingdoms-transfer-completion-security-review.md), [whole increment](kingdoms-transfer-planning-security-review.md).

### `KINGDOMS-003`

[P0](kingdoms-alliance-intelligence-p0-security-review.md), [Tracking](kingdoms-alliance-tracking-security-review.md), [Observations](kingdoms-alliance-observation-security-review.md), [Diplomacy](kingdoms-alliance-diplomacy-security-review.md), [Contacts](kingdoms-alliance-diplomacy-contact-security-review.md), [Dashboard](kingdoms-alliance-intelligence-dashboard-security-review.md), [whole increment](kingdoms-alliance-intelligence-security-review.md).

### `KINGDOMS-004`

- [K4-P0 security/privacy review](kingdoms-automated-ingestion-p0-security-review.md)
- [K4-P1 ingestion foundation security review](kingdoms-automated-ingestion-foundation-security-review.md)
- [Living automated-ingestion contract](../automated-ingestion.md)
- [K4 product evidence](../product/README.md)
- [Security baseline](../../../security/security-baseline.md)
