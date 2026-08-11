# KINGDOMS-003 K3-P0 security and privacy review

[← Security documentation](README.md)

**Scope ID:** `KINGDOMS-003`  
**Gate:** `K3-P0` — identity, tenancy, diplomacy-state, privacy/history contract lock  
**Status:** **Complete — design/security contract locked**  
**Runtime impact:** None; this review governs later implementation slices.

## 1. Review objective

This review validates the pre-runtime security model for Kingdom/alliance intelligence and diplomacy before persistence or first-party workflows are added.

The primary risk is that a neutral, game-observable alliance reference could accidentally become a path around tenant privacy. `K3-P0` therefore treats global game-side identity and tenant-owned intelligence as separate trust boundaries.

## 2. Protected assets

Tenant-owned protected data includes:

- tracked-alliance state and manager notes;
- recorded observations and manager-only provenance;
- observation invalidation/correction rationale;
- diplomacy state history and transition actors;
- private NAP/relationship terms and rationale;
- diplomacy contact handles, verification metadata and notes;
- derived manager intelligence; and
- audit/outbox evidence that could reveal private coordination if payloads are over-broad.

Global neutral data is deliberately narrow:

- Kingdom;
- `KingdomAlliance` stable game alliance ID when known;
- current neutral name/tag; and
- neutral lifecycle metadata.

Global neutral records are not a container for tenant intelligence.

## 3. Trust boundaries

### Active Alliance boundary

The authenticated active platform Alliance remains the authoritative tenant context for all K3-owned observations and workflows.

A caller never gains tenant data merely by knowing a neutral `KingdomAlliance` ID, tracking ID, observation ID, transition ID or contact ID.

### Global neutral identity boundary

`KingdomAlliance` is reference identity only. Sharing the same neutral record across tenants is expected and must not imply shared observations, diplomacy or contacts.

### Manager boundary

`alliance.view` supports approved ordinary read presentation. `kingdoms.manage` plus recent password confirmation protects privileged tracking, observation, diplomacy and contact mutations.

No contact, diplomacy state or coordinator-like designation grants authorization.

### Integration boundary

K3 audit/outbox messages remain internal. Existing `kingdoms.*` webhook exclusion remains the external-exposure boundary. No K3 public API or public event schema is approved.

## 4. Threats and controls

### 4.1 Neutral identity confused with tenant identity

**Threat:** A game-side alliance reference is treated as a platform `Alliance`, causing authorization or ownership confusion.

**Controls:**

- separate canonical concepts: platform `Alliance` versus neutral `KingdomAlliance`;
- no platform-Alliance↔KingdomAlliance ownership linkage in K3;
- neutral references contain no tenant roles/memberships/diplomacy;
- all tenant workflows start from explicit active-Alliance ownership.

### 4.2 Cross-tenant object-ID tampering

**Threat:** An authorized user submits another tenant's tracking, observation, diplomacy or contact ID.

**Controls:**

- re-resolve every tenant-owned identifier under the active Alliance and owning tracked relationship;
- avoid unscoped model binding for tenant-owned mutations;
- feature tests must prove read and mutation failure across tenants;
- tenant-first indexes/foreign-key relationships should support the scoped query pattern.

### 4.3 Name/tag collision causes identity merge

**Threat:** Reused or changed alliance names/tags cause two game-side alliances to merge.

**Controls:**

- stable game alliance ID inside one Kingdom is the only automatic resolution key;
- name/tag never auto-merge or relink;
- unresolved neutral references may coexist with duplicate name/tag values;
- later stable-ID conflicts fail closed and require explicit resolution.

### 4.4 Kingdom drift causes stale-context mutation

**Threat:** A platform Alliance changes Kingdom but continues mutating old-Kingdom diplomacy as if it were current.

**Controls:**

- tracking captures Kingdom context;
- privileged K3 mutations require captured context to match the active Alliance's current Kingdom;
- historical reads remain available under tenant authorization;
- archival remains available as safe stale-context recovery;
- no silent retargeting or Kingdom rewrite.

### 4.5 Diplomacy inferred automatically

**Threat:** Power trends, attacks, transfer activity or dates silently turn a relationship into rival/NAP/etc.

**Controls:**

- diplomacy state is explicit manager input only;
- date thresholds derive `needs_review` only;
- no observation→diplomacy transition path;
- architecture/source tests should reject automated transition services/jobs introduced outside approved scope.

### 4.6 Private NAP/contact data leaks to members

**Threat:** Ordinary alliance members receive handles, notes, terms, actors or correction reasons.

**Controls:**

- explicit member-safe serialization contract;
- manager-only serializers/queries for contact handles, terms and provenance;
- leakage regression tests for rendered/Inertia payloads;
- no private text in audit/outbox metadata;
- no private text in structured logs unless separately reviewed.

### 4.7 Contact becomes identity/authentication shortcut

**Threat:** A Discord/in-game handle is used to create/link a `User`, `AllianceMembership` or `KingdomPlayer`, or to grant permissions.

**Controls:**

- K3 initial contacts have no `KingdomPlayer` linkage;
- contact creation never creates application identity or membership;
- contact fields do not participate in authorization;
- display name/handle are never stable identity keys;
- regression tests verify no role/permission/membership side effects.

### 4.8 Over-collection of personal information

**Threat:** Diplomacy contacts become an uncontrolled personal directory.

**Controls:**

- collect only coordination display name/role/channel/handle/verification state/notes;
- do not solicit phone numbers, home addresses, credentials, recovery material or unrelated personal data;
- contact details remain manager-private;
- normal lifecycle prefers inactive/archive over uncontrolled duplication.

### 4.9 History rewritten or erased

**Threat:** Corrections destroy evidence, allowing current views to hide what was previously recorded.

**Controls:**

- observations are append-oriented;
- invalidation preserves the original record and actor/time;
- corrected facts are new observations;
- diplomacy transitions append history rather than rewriting prior states;
- archive retains tracking/diplomacy history.

### 4.10 Retry creates duplicate observations or transitions

**Threat:** Browser/network retries multiply facts or history.

**Controls:**

- deterministic exact-retry observation fingerprint;
- same-current diplomacy transition is idempotent;
- mutations use transactions/locking where needed;
- audit/outbox rows are created only for material state changes.

### 4.11 Ranking/threat-score abuse

**Threat:** Descriptive data becomes punitive ranking or automated leadership advice.

**Controls:**

- no threat/risk/desirability score fields;
- no default competitive ranking by derived value;
- 7/30-day change remains descriptive factual trend;
- no automated diplomacy recommendation or transfer decision;
- source/architecture tests should reject score/recommendation placeholders.

### 4.12 Internal events become external contracts accidentally

**Threat:** wildcard webhook subscribers receive K3 private event data.

**Controls:**

- K3 event names remain under `kingdoms.*`;
- existing Integrations exclusion applies even to wildcard/exact guessed selectors;
- representative K3 event types must be added to webhook-exclusion regression coverage before acceptance;
- private terms/contact text never enters event payloads.

## 5. History and correction privacy

Observation invalidation and diplomacy transition history can reveal sensitive leadership reasoning if overexposed.

Member-safe history may expose factual observation values and public diplomacy labels only where the approved UI needs them. Actor IDs, correction rationale, NAP terms and contact detail remain manager-private.

Retention/deletion behavior must preserve audit/business history according to existing platform controls without converting private coordination records into public/global facts.

## 6. Migration and rollback security

K3 slices must remain dependency-safe and reversible:

1. neutral alliance references;
2. tenant tracking;
3. observations;
4. diplomacy relationship/transitions;
5. contacts.

Rollback occurs in reverse order.

No slice may introduce compatibility shims or dormant K4/K5 fields merely to ease future work.

## 7. Required verification by later slices

Before `K3-P6` acceptance, repository evidence must include:

- stable-ID versus name/tag identity tests;
- cross-tenant ID tampering tests for every tenant-owned K3 entity;
- Kingdom-drift fail-closed tests;
- member/private payload leakage tests;
- no-contact-permission/no-user-creation tests;
- observation retry and invalidation-history tests;
- diplomacy explicit-transition/no-auto-expiry tests;
- internal webhook exclusion tests for representative K3 event types;
- migration rollback/reapply tests;
- realistic-volume query/N+1 tests;
- accessibility coverage for all first-party workflows; and
- whole-increment security review before acceptance.

## 8. Residual risks and explicit deferrals

This design does not attempt to solve:

- automated/approved game-data ingestion (`KINGDOMS-004`);
- shared/opt-in cross-alliance intelligence (`KINGDOMS-005`);
- public Kingdoms APIs/webhooks;
- automated combat/diplomacy recommendations;
- scraping/OCR/bots/undocumented APIs; or
- production infrastructure controls outside repository evidence.

Those require separate approval and security review.

## 9. P0 security decision

The K3 design is acceptable to proceed to Slice A only while the locked identity/tenancy/privacy/history controls in this review and the companion P0 decision record remain intact.

`K3-P0` creates no runtime capability and does not authorize production deployment.