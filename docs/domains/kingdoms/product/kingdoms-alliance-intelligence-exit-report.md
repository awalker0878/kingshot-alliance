# KINGDOMS-003 alliance intelligence and diplomacy exit report

[← KINGDOMS-003 implementation plan](kingdoms-alliance-intelligence-implementation-plan.md)

**Scope:** `KINGDOMS-003`  
**Acceptance phase:** `K3-P6` — whole-increment hardening + acceptance  
**Status:** **Accepted**  
**Validated implementation SHA:** `068c4086744f71d33453734f1f1b05fe1430cbff`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001` and `KINGDOMS-002`

## 1. Acceptance decision

`KINGDOMS-003` is **Accepted** as a repository/product capability.

The accepted increment delivers one tenant-owned workflow for neutral game-side alliance identity/tracking, append-oriented factual observations, explicit human-maintained diplomacy/NAP history, manager-private diplomacy contacts, and read-only descriptive alliance intelligence.

Acceptance is limited to the repository-controlled product contract. It does **not** approve a real production cutover, automated game-data ingestion, cross-alliance/shared intelligence, public Kingdoms APIs/webhooks, alliance/player scoring, automated diplomacy, automated negotiation, or automatic transfer behavior.

## 2. Accepted domain and identity boundary

The whole-increment review confirms:

- platform `Alliance` remains the tenant and authorization principal;
- `KingdomAlliance` is global neutral game-side reference identity only;
- the approved stable game alliance identifier, scoped within one Kingdom, is the only automatic identity-resolution key;
- names, tags, contact display names and handles never auto-merge, auto-link or retarget identity;
- two tenants may reference the same neutral `KingdomAlliance` while their tracking, observations, diplomacy, contacts, notes, history and derived intelligence remain independent;
- contact records do not create or link `User`, `AllianceMembership`, role, permission or `Player` identity; and
- Alliance-Kingdom drift never silently retargets historical intelligence.

The final end-to-end acceptance test exercises two platform Alliances sharing one neutral stable-ID reference while proving separate tenant-owned tracking and intelligence state.

## 3. Accepted observation and history integrity

Observation history is factual and append-oriented:

- accepted observations retain captured name/tag, optional power/member count, capture time, provenance and actor attribution;
- missing values remain distinct from recorded zero;
- deterministic exact retries are idempotent;
- correction appends a replacement and invalidates the original rather than overwriting history;
- invalidated facts remain manager-visible evidence but are excluded from current/member projections;
- latest projection is based on accepted capture-time ordering rather than insertion order;
- private correction/invalidation reasons remain manager-private and are excluded from audit/outbox payloads; and
- observations never infer diplomacy state or a competitive score.

Whole-increment acceptance re-exercises correction/history behavior inside the full tracking → observation → diplomacy → contact → dashboard workflow.

## 4. Accepted diplomacy and contact behavior

Diplomacy remains explicitly human-maintained with the locked state vocabulary:

- `unknown`;
- `neutral`;
- `friendly`;
- `nap`;
- `ally`; and
- `rival`.

Material state/metadata changes append transition history. Exact repeat of the current normalized meaning is idempotent. Effective/review/expiry metadata is advisory; elapsed review/expiry time creates a human-review indicator and never changes state automatically.

Manager-private contact records provide only the approved coordination fields. They use active/inactive lifecycle preservation, do not become authentication or identity records, and do not grant authorization. Private contact names/handles/notes remain absent from member payloads and durable event metadata.

## 5. Tenancy, authorization and drift review

Whole-increment acceptance confirms:

- ordinary safe intelligence reads use `alliance.view`;
- tracking, observation, diplomacy and contact mutations use `kingdoms.manage` plus recent password confirmation;
- submitted tracking/observation/contact IDs are re-resolved under the active Alliance;
- shared neutral references never grant cross-tenant access;
- cross-tenant object substitution fails closed;
- archived tracking and stale Kingdom context preserve authorized historical reads while blocking normal privileged mutation; and
- archival remains the explicit terminal recovery action after Kingdom drift rather than silent retargeting.

The P6 end-to-end test verifies history/diplomacy/contact reads after Kingdom drift, rejects new observation/diplomacy/contact mutations, and then archives the stale tracking relationship.

## 6. Privacy, security and abuse review

The accepted whole-increment security review confirms:

- member payloads exclude manager tracking notes, observation correction/invalidation reasons, diplomacy terms/rationale, contact details and management provenance;
- manager dashboard contact diagnostics are aggregate only and do not copy contact display names, roles, handles or notes;
- private tracking/correction/diplomacy/contact text is excluded from K3 audit/outbox payloads;
- contact data cannot create an identity/authorization path;
- no threat, target, desirability or composite score exists;
- no punitive ranking or automated recommendation exists;
- no automatic diplomacy/negotiation/transfer action exists; and
- no scraper, OCR worker, bot, automated game-ingestion path, cross-tenant shared-intelligence contract or future-slice placeholder was introduced.

The final acceptance architecture suite directly guards those non-capabilities.

## 7. Descriptive intelligence and query hardening

The accepted dashboard is read-only and descriptive. It provides:

- active tracked-alliance counts;
- current/stale/missing observation quality;
- latest safe name/tag/power/member facts;
- immediately prior accepted-observation changes;
- bounded non-interpolating 7-day comparisons using accepted points 7–14 days before the dashboard time;
- bounded non-interpolating 30-day comparisons using accepted points 30–60 days before the dashboard time;
- explicit diplomacy-state counts and review-needed counts;
- manager-only aggregate contact availability/verification diagnostics; and
- fixed-vocabulary filters with neutral default ordering.

The realistic-volume gate models:

- 120 tracked game-side alliances;
- 600 observations;
- 120 diplomacy relationships; and
- 60 active contacts.

The manager projection remains bounded to **10 or fewer SELECT statements** at that volume. Tenant-first indexes and bounded history/query patterns are protected by architecture and performance tests.

## 8. Migration and rollback/reapply review

Acceptance includes two migration perspectives:

1. the existing complete Kingdoms dependency-order round trip; and
2. a dedicated K3-only rollback/reapply to the exact accepted `KINGDOMS-002` baseline.

The K3-only gate rolls down, in dependency order:

1. diplomacy contacts;
2. diplomacy relationships/transitions;
3. alliance observations; and
4. external-alliance tracking/reference identity.

It verifies accepted K2 roster/snapshot/transfer-completion tables remain intact, then reapplies K3 in forward order and verifies the entire K3 schema returns successfully.

No compatibility column, dormant future schema or migration shim is retained.

## 9. Accessibility review

The accepted accessibility review covers all first-party K3 surfaces:

- tracked game-side alliance member list;
- tracking management workspace;
- observation/history workspace;
- diplomacy/NAP workspace;
- manager-private contact workspace; and
- descriptive intelligence dashboard.

Source-level gates verify semantic `main`/`h1` structure, native controls, explicit form labels, table headers/overflow handling, intelligence filter labels, and an accessible table caption. Member/private presentation is also exercised through feature tests rather than relying only on markup inspection.

See [KINGDOMS-003 accessibility review](kingdoms-alliance-intelligence-accessibility.md).

## 10. Audit, outbox and external integration review

Material privileged K3 mutations remain attributable through internal audit/outbox evidence while excluding private free text.

Representative K3 event families are explicitly tested against an enabled wildcard webhook subscription and produce **zero external deliveries**, including:

- tracking started;
- observation recorded/corrected;
- diplomacy transitioned; and
- diplomacy contact saved/deactivated.

No public Kingdoms API route/scope is introduced. `routes/api.php` remains protected by architecture tests against K3 route/scope exposure.

## 11. Operations and observability review

The accepted operations contract provides diagnostic context for:

- neutral identity resolution/conflict failures;
- active-Alliance/Kingdom invariant failures;
- observation validation/idempotency/correction failures;
- stale/missing data quality;
- diplomacy transition validation;
- contact tenant/validation failures;
- authorization/object-ID failures; and
- internal outbox publication failures.

Private tracking notes, correction reasons, diplomacy terms/rationale and contact text are not general logging/event payload data.

No recurring crawler, game-ingestion worker or diplomacy scheduler is required for the accepted runtime.

## 12. Exact protected validation evidence

Exact validated whole-increment implementation SHA:

`068c4086744f71d33453734f1f1b05fe1430cbff`

Protected runs:

- Dependency Review `31430279647` — **success**;
- CodeQL `31430279652` — **success**;
- CI `31430279638` — **success**.

CI evidence:

- PHP 8.5.9;
- Composer manifest/lock validation — success;
- Composer audit — no security vulnerability advisories;
- PostgreSQL migrations — success through `2026_08_10_100000_create_kingdom_alliance_diplomacy_contacts`;
- Pint — **483 files**;
- PHPStan/Larastan — **345/345, 0 errors**;
- ParaTest/PHPUnit — **359 tests, 4,824 assertions**;
- frontend lockfile/dependency audit — success;
- ESLint — success;
- pinned Prettier — success;
- Vue/TypeScript — success;
- production frontend build — success;
- immutable production image build — success;
- ephemeral staging deployment — success;
- backup/restore demonstration — success;
- image vulnerability scan — success; and
- staging cleanup — success.

## 13. Final disposition

`KINGDOMS-003` is **Accepted** for repository/product purposes.

Accepted capability now includes neutral game-side alliance tracking, append-oriented factual observations/history, explicit diplomacy/NAP lifecycle, manager-private diplomacy contacts, and descriptive alliance intelligence under the established tenant/privacy/security boundaries.

Deferred work remains deferred. In particular, automated game-data ingestion (`KINGDOMS-004`), any future opt-in/shared Kingdom intelligence (`KINGDOMS-005` candidate), public Kingdoms API/webhook contracts, scoring/ranking, and automatic game/diplomacy/transfer behavior are not implied by this acceptance.

A real production cutover remains separately **not yet approved** and continues to be governed by the production launch approval record.
