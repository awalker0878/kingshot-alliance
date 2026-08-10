# KINGDOMS-003 Slice A security review

[← Security documentation](README.md)

**Scope:** `KINGDOMS-003` Slice A / `K3-P1` — neutral game-side alliance identity and tenant tracking foundation  
**Status:** Candidate pending protected validation

## Assets and trust boundaries

Slice A introduces:

- global neutral `KingdomAlliance` identity;
- alliance-owned `TrackedKingdomAlliance` tracking state;
- manager-private tracking notes;
- member-safe tracking presentation; and
- internal audit/outbox evidence.

The platform `Alliance` remains the tenant and authorization principal. A neutral game-side alliance reference grants no authentication, membership, permission or cross-tenant access.

## Threats and controls

### Platform Alliance / KingdomAlliance identity confusion

**Risk:** a game-side alliance reference could accidentally become a tenant identity or authorization path.

**Controls:**

- separate model/table types;
- no platform-Alliance foreign key on the neutral reference;
- all tenant-owned state lives on `TrackedKingdomAlliance`;
- authorization always evaluates the active platform Alliance; and
- architecture tests require K3 runtime ownership to remain under the Kingdoms domain without introducing public API contracts.

### Name/tag collision and accidental merge

**Risk:** common/reused names or tags could collapse distinct game-side alliances.

**Controls:**

- name/tag are never lookup keys for automatic resolution;
- no stable ID means a new neutral reference is created deliberately;
- same-Kingdom stable game alliance ID is the only automatic reuse key; and
- feature coverage proves duplicate name/tag creates distinct references.

### Stable-ID takeover or reassignment

**Risk:** a manager could relink a neutral reference by changing its stable game alliance ID.

**Controls:**

- stable ID is assign-once;
- an existing stable ID cannot be cleared/replaced in place;
- assigning an ID owned by another same-Kingdom reference fails closed; and
- updates lock the tenant, tracking row and neutral reference transactionally.

### Cross-tenant object-ID tampering

**Risk:** a manager could submit another tenant's tracking ID.

**Controls:**

- update/archive re-resolve `TrackedKingdomAlliance` beneath active `alliance_id`;
- missing cross-tenant IDs return not found;
- member and manager reads query explicitly by active Alliance; and
- feature tests cover update/archive tampering.

### Shared neutral reference leaks private tenant data

**Risk:** two tenants tracking the same stable-ID reference could accidentally share notes or lifecycle state.

**Controls:**

- manager notes exist only on tenant tracking rows;
- member payloads omit manager notes and management IDs;
- one tenant may reuse neutral current identity while retaining a distinct tracking row; and
- feature coverage verifies different private notes remain separate.

### Alliance-Kingdom drift

**Risk:** a tracking row could silently become associated with the Alliance's new Kingdom after a tenant Kingdom change.

**Controls:**

- tracking captures `kingdom_id` at creation;
- ordinary edits require captured Kingdom == current Alliance Kingdom;
- historical read remains available;
- archive is explicitly allowed as stale-context recovery; and
- captured Kingdom is never rewritten automatically.

### Privilege escalation

**Risk:** ordinary members or platform administrators could mutate tracking without Kingdoms authority.

**Controls:**

- member reads use `alliance.view`;
- manager reads/mutations use `kingdoms.manage`;
- mutations require recent password confirmation;
- no role-name checks or new K3 permission are introduced; and
- tests verify ordinary members cannot manage/mutate.

### Private-note leakage through audit/outbox

**Risk:** manager notes could become durable integration/log payload data.

**Controls:**

- audit/outbox metadata contains identifiers/state booleans only;
- note text is never copied into event metadata;
- feature tests search durable audit/outbox payloads for private-note sentinel text; and
- all `kingdoms.*` events remain excluded from generic external webhook fan-out.

### Premature future-scope capability

**Risk:** Slice A schema/routes could quietly introduce observations, diplomacy, contacts, automated ingestion, sharing or scoring.

**Controls:**

- Slice A migration contains only neutral identity and tenant tracking fields;
- routes expose only tracking read/manage/create/update/archive behavior;
- architecture tests reject observation/diplomacy/contact/ingestion/scoring placeholders; and
- no public Kingdoms API/webhook route/scope is added.

## Residual risks

Neutral current name/tag is shared reference data. An authorized manager tracking a shared stable-ID reference may update those current neutral display fields, which can affect how that reference appears to another tenant. This is intentionally limited to non-private reference identity; tenant observations/history arrive in later slices and remain tenant-owned. If stronger neutral-reference governance is required, it must be added explicitly rather than moving tenant observations onto the global row.

A stable game alliance ID is trusted only to the degree the approved data source/operator input is trustworthy. Slice A does not claim external verification or automated ingestion.

## Verification required for validation

- PostgreSQL migration and rollback/reapply;
- stable-ID reuse and collision behavior;
- duplicate name/tag no-merge behavior;
- cross-tenant ID isolation;
- password-confirmation/authorization gates;
- drift/archive recovery;
- member/private field split;
- private-note audit/outbox exclusion;
- accessibility checks on both first-party pages;
- existing `kingdoms.*` webhook exclusion; and
- protected dependency, static-analysis, tests, image, staging, recovery and scan gates.

Repository/product validation of Slice A does not approve a production cutover or automated game-data ingestion.
