# Kingdoms Alliance intelligence and diplomacy

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — `KINGDOMS-003` accepted; factual observation source extended by governed `KINGDOMS-004` K4-P3 machine promotion  
**Owning domain:** `Kingdoms`

## 1. Purpose

Alliance intelligence and diplomacy owns neutral game-side Alliance identity plus Alliance-owned tracking, factual observation history, explicit diplomacy/NAP state/history, minimal manager-private contacts, and descriptive intelligence.

`KINGDOMS-003` remains the accepted business contract. K4-P3 extends only the factual observation input boundary: an approved ingestion candidate may append an observation for an already active tracked game Alliance after stable-ID/current-Kingdom/tenant checks. Tracking lifecycle, correction/invalidation, diplomacy, contacts, and decisions remain human-owned.

## 2. Scope and non-scope

In scope: neutral `KingdomAlliance`; tenant `TrackedKingdomAlliance`; factual observation append/correction/invalidation; explicit diplomacy; manager-private contacts; descriptive intelligence; and K4-P3 delegated machine **append** observations with bounded provenance.

Out of scope: machine tracking creation/reactivation; machine correction/invalidation; cross-tenant/shared intelligence; source scraping/OCR/bots; threat/desirability/target scoring; automated diplomacy/negotiation/transfer; and public Kingdoms API/webhook schemas.

## 3. Model and state

`Alliance` remains tenant authority. `KingdomAlliance` is neutral game identity within one Kingdom; stable `game_alliance_id` is its only automatic identity key. `TrackedKingdomAlliance` is the tenant-owned relationship and lifecycle.

`KingdomAllianceObservation` remains append-oriented tenant history. Manual observations retain User actor and optional correction/invalidation governance. `source=ingestion` uses null User actor plus bounded subscription/batch/adapter/source-record/hash provenance and cannot carry correction/invalidation instructions.

Diplomacy vocabulary and contact model are unchanged from K3.

## 4. Invariants

1. Neutral `KingdomAlliance` sharing never shares tenant observations/diplomacy/contacts/notes.
2. Stable game Alliance ID within one Kingdom is the only automatic identity key.
3. Machine promotion requires an existing active owning-Alliance tracking relation; it never creates/reactivates tracking.
4. Observation history is append-oriented.
5. Correction appends replacement and invalidates original only through explicit human manager action.
6. Machine observations cannot correct/invalidate prior history.
7. Missing remains distinct from zero.
8. Diplomacy changes only through explicit human manager transitions and is never inferred from observations.
9. Contacts remain manager-private and never become identity/authorization.
10. Intelligence remains descriptive; no threat/target/desirability/composite score exists.
11. Dashboard reads emit no new audit/outbox event.
12. All K3/K4 Kingdoms events remain external-webhook ineligible.

## 5. Workflows

Managers establish/archive tracking, record/correct/invalidate observations, maintain diplomacy/contacts, and view descriptive intelligence under the accepted K3 workflows.

K4-P3 machine promotion rechecks tenant/captured/current Kingdom, registered adapter version, active neutral reference, and exactly one active owning-Alliance tracking relationship. It then delegates factual `observed_name`, optional tag/power/member count, and capture time to `RecordKingdomAllianceObservation` with `source=ingestion` and bounded machine provenance.

Exact candidate retry returns the same observation. A later distinct capture appends another observation and participates in the accepted latest/trend/neutral-current-name/tag projection. It never supplies `corrects_observation_id` or correction reason.

## 6. Authorization, tenancy and privacy

Member dashboard/history reads require `alliance.view`; human mutations require `kingdoms.manage` plus recent password confirmation. Machine promotion derives authority from validated tenant-owned ingestion state only.

Member observation output excludes actor and machine provenance. Managers may see bounded source provenance in history. Diplomacy/contact private text remains outside machine ingestion and member output.

## 7. Persistence and query semantics

Accepted K3 observation/correction/invalidation persistence remains authoritative. K4-P3 adds nullable machine provenance fields and Alliance/source-identity uniqueness to observations; there is no FK from canonical observation history to operational ingestion rows.

Intelligence projection remains non-persistent and retains accepted current/prior/7-day/30-day selection and ≤10 SELECT manager performance gate at 120 tracked Alliances / 600 observations / 120 diplomacy relationships / 60 contacts.

## 8. Events/integrations/background processing

New machine observations reuse internal `kingdoms.alliance_intelligence_observation_recorded`; candidate promotion emits internal `kingdoms.ingestion_candidate_promoted`. Machine promotion never emits the K3 correction event because machine correction is prohibited.

There is still no K4 scheduler/source poller/crawler/scraper/OCR/bot/public API or webhook schema.

## 9. Failure, idempotency and concurrency

Exact observation/candidate retry is idempotent. Unknown/ambiguous/inactive neutral references, missing/inactive/ambiguous owning-Alliance tracking, Kingdom drift, revoked source version, and invalid candidate facts quarantine before observation mutation.

Human correction semantics remain unchanged and preserve original evidence. Cross-tenant IDs/references fail closed.

## 10. Operations and observability

Operators can distinguish manual versus ingestion observation source and, for authorized manager/operator paths, correlate bounded source provenance to K4 candidate state. Do not use name/tag guessing, tracking creation/reactivation, diplomacy changes, or history invalidation as ingestion recovery.

See [Alliance intelligence operations](operations/kingdoms-alliance-intelligence.md) and [Automated ingestion operations](operations/kingdoms-automated-ingestion.md).

## 11. Tests and validation

K3 whole-increment acceptance remains historical evidence. K4-P3 runtime candidate `8186af9fd7276a20889ca3a25b80172c6fe824d9` passed DR `31541291512`, CodeQL `31541291470`, CI `31541291501`: Pint 515, PHPStan 365/365 zero errors, 417 tests / 9,628 assertions, image/staging/backup/scan success.

P3 focused tests prove active-tracking stable-ID promotion, no fake User actor, bounded provenance, exact retry/later append history, cross-tenant no-auto-tracking, inactive tracking quarantine, unknown neutral reference, source revocation, and migration round-trip.

## 12. Related documentation

- [Kingdoms domain](README.md)
- [Automated ingestion](automated-ingestion.md)
- [K4 Slice C validation](product/kingdoms-automated-ingestion-slice-c-validation.md)
- [K4 Slice C security review](security/kingdoms-automated-ingestion-alliance-promotion-security-review.md)
- [KINGDOMS-003 implementation plan](product/kingdoms-alliance-intelligence-implementation-plan.md)
- [KINGDOMS-003 exit report](product/kingdoms-alliance-intelligence-exit-report.md)
