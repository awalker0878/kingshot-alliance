# KINGDOMS-003 Slice D validation

[← KINGDOMS-003 implementation plan](kingdoms-alliance-intelligence-implementation-plan.md)

**Scope:** `KINGDOMS-003` Slice D / `K3-P5`  
**Status:** Validated; `KINGDOMS-003` remains In progress  
**Validated runtime SHA:** `a9d2e22ea1c710bc72f4dc8824a70e15dda04e75`  
**Validated dependency / Slice C2 docs head:** `6f71870d56aa9729d19d3644e33efdc158241d81`

## Validated contract

Slice D adds a read-only Alliance intelligence dashboard over the validated tracking, observation, diplomacy and contact slices.

Validated runtime behavior includes:

- active-Alliance tenant isolation for every aggregate/projection query;
- latest accepted observation and deterministic immediately-prior accepted observation;
- invalidated/future observation exclusion;
- missing-versus-zero preservation;
- bounded non-interpolating 7-day baseline selection from 7–14 days before `asOf`;
- bounded non-interpolating 30-day baseline selection from 30–60 days before `asOf`;
- factual power/member deltas only where both endpoint values exist;
- active tracked-alliance observation-quality and diplomacy-state headline counts;
- advisory relationship-review counts without automatic diplomacy transition;
- member-safe current/freshness/trend/diplomacy detail;
- manager-only aggregate contact availability/verification diagnostics without contact text;
- validated fixed-vocabulary filters and factual navigation sorting;
- neutral default ordering by alliance name;
- no composite/threat/desirability/target score or best/worst ranking;
- no automated recommendation, negotiation, diplomacy or transfer behavior;
- no new persistence/migration/audit/outbox side effect;
- no public Kingdoms API/webhook contract;
- accessible filters/table/status language; and
- realistic-volume batched query behavior at 120 tracked alliances / 600 observations / 120 diplomacy relationships / 60 contacts with no more than 10 SELECTs for the manager projection.

## Protected validation evidence

The exact validated runtime SHA `a9d2e22ea1c710bc72f4dc8824a70e15dda04e75` passed every protected repository gate.

### Protected workflows

- Dependency Review run `31414124893` — **success**
- CodeQL run `31414124920` — **success**
- CI run `31414124902` — **success**

### CI jobs

- PHP quality and tests job `93538968735` — **success**
- Frontend quality and build job `93538968819` — **success**
- Container, staging, and recovery job `93539341055` — **success**

### PHP and PostgreSQL

- PHP `8.5.9`
- Composer validation — **success**
- Composer dependency audit — **no security vulnerability advisories found**
- PostgreSQL migrations through `2026_08_10_100000_create_kingdom_alliance_diplomacy_contacts` — **success**
- Slice D adds no migration
- Pint — **481 files passed**
- PHPStan/Larastan — **345/345 files, 0 errors**
- ParaTest `7.20.0` / PHPUnit `12.5.33` — **353 tests, 4,452 assertions**

The feature/architecture/performance suite validates accepted-only history selection, deterministic latest/prior points, bounded 7/30-day baselines, too-new/too-old baseline rejection, missing-versus-zero behavior, diplomacy-review diagnostics, member/manager contact privacy, private contact-text exclusion, fixed filter/sort vocabulary, cross-tenant isolation, absence of scoring/recommendation/automatic-action contracts, accessibility/source boundaries and realistic-volume query behavior.

The performance gate models **120 tracked alliances, 600 accepted observations, 120 diplomacy relationships and 60 contacts** and enforces **no more than 10 SELECT statements** for the manager intelligence projection.

### Frontend

- npm lockfile verification — **success**
- dependency audit — **success**
- ESLint — **success**
- pinned Prettier — **success**
- Vue/TypeScript checks — **success**
- production build — **success**

### Operational controls

- scripts/exclusions/Compose validation — **success**
- immutable production image build — **success**
- ephemeral staging deployment — **success**
- backup/restore demonstration — **success**
- image vulnerability scan — **success**
- cleanup — **success**

## Validation-anchor hygiene

A temporary frontend diagnostic was used only to obtain the repository's exact pinned Prettier rewrite for the new Vue dashboard. It was not used as the validation anchor. `package.json` was restored completely and does not appear in the final Slice D diff from the validated Slice C2 base.

The runtime validation anchor is exactly `a9d2e22ea1c710bc72f4dc8824a70e15dda04e75`. A later documentation-only status commit may sit above that runtime SHA without replacing it.

## Exit status

`K3-P5` is **Validated**.

`KINGDOMS-003` remains **In progress**. Whole-increment hardening/acceptance remains `K3-P6`.

This validation does not mark the whole increment Accepted and does not approve a production deployment or cutover.
