# KINGDOMS-003 Slice B validation

[← KINGDOMS-003 implementation plan](kingdoms-alliance-intelligence-implementation-plan.md)

**Scope:** `KINGDOMS-003` Slice B / `K3-P2`  
**Status:** Validated  
**Validated runtime SHA:** `bf064075971ce0f81bd800b5ce0c5c88c9c1010c`

## Validation result

The complete Slice B runtime, tests, accessibility guards, domain/security/operations documentation and restored repository manifests passed the protected repository gate on the exact runtime SHA above.

Temporary diagnostic heads used while resolving PostgreSQL identifier/self-reference behavior and printing pinned Pint/Prettier rewrites are not acceptance evidence. `package.json` and `composer.json` were restored to the Slice A baseline before the validated runtime SHA and are absent from the Slice B net diff.

## Protected workflow evidence

- Dependency Review `31342802384`: **success**
- CodeQL `31342802370`: **success**
- CI `31342802361`: **success**
  - npm lockfile/install and dependency advisory check: success
  - ESLint, repository-pinned Prettier, Vue/TypeScript and production build: success
  - Composer manifest/lock validation and dependency audit: success
  - PostgreSQL migrations, including `2026_08_09_150000_create_kingdom_alliance_observations.php`: success
  - Pint: **459 files passed**
  - PHPStan/Larastan: **329 files / 0 errors**
  - ParaTest/PHPUnit: **323 tests / 3851 assertions**
  - immutable production image build/identification: success
  - ephemeral staging deployment: success
  - backup/restore demonstration: success
  - image vulnerability scan: success
  - staging cleanup: success

## Validated Slice B behavior

Protected coverage and repository guards validate:

- append-oriented tenant observation history;
- latest accepted projection by capture time with ULID tie-break rather than insertion order;
- deterministic exact-retry idempotency and no duplicate durability events;
- optional power/member facts with missing-versus-zero semantics;
- signed-64-bit power bounds and safe decimal-string browser serialization;
- capture-time future bound and first-party local-to-ISO/UTC conversion;
- correction by append + transactional invalidation of the original;
- standalone invalidation preserving history and remaining idempotent;
- invalidated rows excluded from member latest/freshness projections while retained for manager history;
- neutral current name/tag reprojected from the latest accepted neutral-reference observation;
- current/stale/missing freshness using the accepted 30-day Kingdoms threshold;
- active-Alliance re-resolution for tracking/observation IDs and cross-tenant fail-closed behavior;
- Alliance-Kingdom drift preserving historical reads while blocking observation mutation;
- `alliance.view` safe reads and `kingdoms.manage` plus recent password confirmation for mutations;
- member-safe factual history separated from manager actor/correction/invalidation/private-reason detail;
- private correction/invalidation reason text excluded from audit/outbox metadata;
- bounded observation history and tenant-first indexes;
- migration rollback/reapply through K3 → K2 → K1 dependency order;
- source-level accessibility requirements; and
- architecture guards excluding diplomacy/NAP, contacts, threat/ranking/scoring, automated recommendations, ingestion/scraping/OCR/bots, cross-tenant intelligence sharing and public Kingdoms API/webhook contracts.

## Acceptance boundary

`K3-P2` is **Validated**. `KINGDOMS-003` remains **In progress** and is not whole-increment Accepted.

Next planned slice: `K3-P3` / Slice C1 — explicit diplomacy/NAP lifecycle and transition history.

Repository/product validation does not approve a real production cutover; production launch remains separately governed.
