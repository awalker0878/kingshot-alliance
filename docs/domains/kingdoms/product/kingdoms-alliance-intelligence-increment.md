# Kingdoms alliance intelligence and diplomacy product increment

[← Product and program documentation](README.md)

**Status:** Approved scope — implementation **Accepted**  
**Scope ID:** `KINGDOMS-003`  
**Owning domain:** `Kingdoms`  
**Delivery model:** Post-program product increment; this is **not Phase 7**  
**Baseline dependency:** Accepted `KINGDOMS-001` roster/player identity and `KINGDOMS-002` transfer-planning controls  
**Implementation sequence:** [KINGDOMS-003 implementation plan](kingdoms-alliance-intelligence-implementation-plan.md)  
**Acceptance evidence:** [KINGDOMS-003 exit report](kingdoms-alliance-intelligence-exit-report.md)

## 1. Purpose

`KINGDOMS-003` adds alliance-owned intelligence and diplomacy coordination for other game-side alliances in the Alliance's current Kingdom.

The increment gives alliance leadership one controlled workspace to answer:

- which game-side alliances are being tracked in the current Kingdom;
- what stable identity, name and tag are known for each tracked alliance;
- what power/member observations have actually been recorded and when;
- whether those observations are current, stale or missing;
- what diplomacy state the Alliance currently maintains with a tracked alliance;
- what NAP/ally/friendly/rival history produced the current relationship;
- which manager-maintained diplomacy contacts are associated with the tracked alliance; and
- what changes require human review without converting observations into rankings, threat scores or automated recommendations.

The workflow is alliance-owned intelligence and coordination. It does not expose another platform Alliance's private data, infer diplomacy from combat/game behavior, scrape game data, rank alliances, automate transfer decisions, or create a public kingdom-intelligence service.

## 2. Product outcome

Alliance members can see approved, current diplomacy/intelligence information for game-side alliances their own Alliance tracks without relying only on spreadsheets or chat history.

Authorized Kingdoms managers can maintain external-alliance identity references, append-oriented observations, diplomacy state/history and manager-private contacts while preserving attribution and tenant boundaries.

The increment extends the accepted Kingdoms identity model rather than treating a game-side alliance tag/name as a platform tenant identity.

## 3. Core business rules

### Game-side alliance identity is not platform Alliance identity

`Alliance` remains the platform tenant aggregate owned by the Alliances domain.

A neutral `KingdomAlliance` reference represents an observed game-side alliance inside one `Kingdom`. It is reference identity only and does not create membership, authorization, tenant access, or a platform Alliance.

A `KingdomAlliance` may exist even when no corresponding platform Alliance exists. The accepted increment does not automatically link a neutral game-side alliance reference to a platform Alliance account.

### Stable game alliance ID is the only automatic identity key

When an approved stable game-side alliance identifier is known, it may be used to resolve one `KingdomAlliance` inside the same Kingdom.

Alliance name and tag are display/observed data. A tag or name alone is never sufficient to auto-merge, relink or deduplicate game-side alliance identity.

Tag/name changes preserve historical observations rather than rewriting all prior history.

### Neutral references are global; intelligence remains tenant-owned

`Kingdom` and `KingdomAlliance` are global neutral reference data.

Tracking state, observations, diplomacy relationship, NAP terms/notes, contacts, private notes and derived intelligence are owned by one platform Alliance.

Two platform Alliances may reference the same neutral `KingdomAlliance`; that does not allow either tenant to read the other's observations, diplomacy state, contacts, notes, history or derived summaries.

### Current-Kingdom context fails closed

`KINGDOMS-003` tracking is limited to game-side alliances in the active Alliance's current Kingdom.

A tracked relationship captures/revalidates that Kingdom context. If the platform Alliance later changes Kingdom, existing old-Kingdom intelligence remains historical/readable to authorized users but privileged mutation fails closed until leadership deliberately archives/reconciles the old tracking context or creates tracking under the new Kingdom.

The system does not silently retarget tracked alliances or diplomacy records after an Alliance Kingdom change.

### Observations are facts, not scores

Alliance power, member count, name/tag and comparable game-side facts are recorded as time-stamped observations with provenance.

Observations are append-oriented. Current views project the latest accepted observation while historical values remain available for trend and data-quality analysis.

Missing data is not zero. Stale information is not represented as current.

The product does not derive a threat score, desirability score, combat prediction, diplomacy recommendation or punitive ranking from observations.

### Diplomacy is explicit human-maintained state

Diplomacy state is explicitly selected by an authorized manager. The accepted state vocabulary is exactly `unknown`, `neutral`, `friendly`, `nap`, `ally`, and `rival`.

Relationship changes preserve append-oriented transition history with actor/effective-time attribution.

Optional effective/review/expiry dates are planning metadata. Reaching a review/expiry date does not automatically change diplomacy state; the UI shows that human review is required.

No combat result, roster change, power trend, transfer plan or other observed metric automatically changes diplomacy state.

### Contacts are coordination data, not identity or authorization

Diplomacy contacts are alliance-owned coordination records associated with a tracked game-side alliance.

A contact may contain an in-game display name/role and an approved external handle/channel identifier needed for diplomacy coordination. Contact details and notes are manager-private.

Contact assignment never grants platform permissions, application membership, `kingdoms.manage`, or access to another Alliance.

The accepted increment does not collect phone numbers, home addresses, private credentials, secrets or unrelated personal data. A contact handle is not a stable Player identity key, and K3 contacts do not link to `Player`, `User`, memberships, roles or permissions.

### Member-safe and manager-private presentation remain distinct

Ordinary authenticated visibility uses `alliance.view` and may include approved tracked-alliance name/tag, latest safe observation summary, current diplomacy label and freshness indicators.

`kingdoms.manage` is required for tracking, observations, diplomacy transitions, contact maintenance and private management detail.

Member payloads exclude private diplomacy notes/terms, contact handles, contact notes, transition actors, manager-only observation provenance and internal IDs not required by the member workflow.

Privileged mutations require recent password confirmation under the accepted Kingdoms mutation pattern.

### Integration events remain internal

`kingdoms.alliance_intelligence_*` / `kingdoms.diplomacy_*` outbox events are durable internal evidence only.

They remain ineligible for generic outbound webhook fan-out, including wildcard subscriptions, unless a later explicitly approved integration contract defines public event schemas.

`KINGDOMS-003` introduces no public Kingdoms API or cross-alliance intelligence API.

## 4. In-scope capabilities

### 4.1 Neutral game-side alliance references

Provide a first-class `KingdomAlliance` reference with at minimum:

- ULID identity;
- owning `Kingdom` reference;
- optional approved stable game alliance identifier;
- current neutral display name;
- current neutral alliance tag where known;
- lifecycle state; and
- timestamps.

Provide an alliance-owned tracking relationship from the active platform Alliance to the neutral reference, including current-Kingdom context, active/archived tracking state and manager-only notes as required.

### 4.2 Alliance observations

Provide alliance-owned append-oriented observations supporting:

- tracked game-side alliance reference;
- observed alliance name/tag;
- power when supplied;
- member count when supplied;
- capture time;
- provenance/source;
- actor where applicable; and
- safe invalidation/correction semantics that preserve the original observation rather than deleting history.

Manual entry is the accepted `KINGDOMS-003` source. Automated game-data ingestion remains a separate `KINGDOMS-004` scope.

### 4.3 Diplomacy relationship and history

Provide one current alliance-owned diplomacy relationship per tracked game-side alliance plus append-oriented transition history.

Support:

- explicit relationship state;
- effective time;
- optional review/expiry time;
- manager-private rationale/terms where required;
- actor attribution;
- transition history; and
- derived “needs review” presentation without automatic state mutation.

### 4.4 Diplomacy contacts

Provide manager-maintained contacts associated with a tracked game-side alliance, including only the minimum coordination information required by the approved workflow:

- display name;
- game-side role/title where useful;
- contact channel/type;
- handle/identifier;
- last-verified time;
- active/inactive state; and
- manager-private notes.

Contact records remain tenant-owned and management-private. The increment does not create a public contact directory or player/account identity linkage.

### 4.5 Alliance intelligence views

Provide authenticated alliance intelligence views that derive from tracked relationships and observations:

- current tracked-alliance list;
- current name/tag and latest recorded power/member count;
- missing/stale/current observation indicators;
- prior-observation and bounded 7/30-day change where sufficient history exists;
- current diplomacy state;
- relationship expiry/review-needed indicators;
- manager-only contact and history detail; and
- filters by diplomacy state, freshness and tracking state.

Default presentation does not rank alliances by power, growth, “threat”, desirability or diplomacy priority. Comparisons are descriptive/diagnostic and use neutral ordering such as name/tag unless the user explicitly chooses a factual sortable column.

### 4.6 History and auditability

Preserve:

- observed game-side alliance history;
- diplomacy transition history;
- contact verification/change attribution where materially required;
- privileged audit evidence; and
- durable internal outbox evidence for material changes.

Historical records are not silently rewritten when a neutral alliance changes name/tag or a diplomacy state changes.

## 5. Authorization model

`KINGDOMS-003` reuses the accepted Kingdoms authorization boundary:

- ordinary authenticated intelligence visibility: `alliance.view`;
- tracking, observation, diplomacy and contact mutations: `kingdoms.manage`;
- Alliance → Kingdom association remains `alliance.manage` and is not moved into the intelligence workflow.

Built-in role defaults remain unchanged from `KINGDOMS-001`: Owner, Leader and Officer receive `kingdoms.manage`; Recruiter, Event Coordinator, Content Manager and Member do not.

Custom-role permission union semantics remain authoritative. There are no controller role-name checks.

Privileged mutations require recent password confirmation. Platform administrators do not implicitly become alliance intelligence/diplomacy managers; cross-tenant support requires an explicit Platform-domain workflow.

## 6. Data ownership

| Concept | Ownership | Tenant scope |
| --- | --- | --- |
| Kingdom | Kingdoms | Global reference |
| Game-side alliance identity (`KingdomAlliance`) | Kingdoms | Global neutral reference |
| Alliance tracking relationship | Kingdoms | Alliance-scoped |
| Game-side alliance observation | Kingdoms | Alliance-scoped |
| Diplomacy relationship | Kingdoms | Alliance-scoped |
| Diplomacy transition history | Kingdoms | Alliance-scoped |
| Diplomacy contact | Kingdoms | Alliance-scoped |
| Platform Alliance | Alliances | Platform tenant |
| Application user | Identity | Global |
| Alliance membership | Memberships | Alliance-scoped |
| Audit event | Audit | Correlated to actor/alliance as applicable |
| Durable internal event | Platform outbox | Alliance-scoped where tenant data is involved |

Global `KingdomAlliance` records do not contain one tenant's diplomacy state, contacts, private notes, observations or derived intelligence.

## 7. Cross-domain contracts

### Alliances

The active platform Alliance establishes tenant context and current Kingdom. `KINGDOMS-003` never treats a neutral `KingdomAlliance` as a platform tenant or authorization principal.

### Kingdoms roster/player identity

Diplomacy contacts remain separate from accepted neutral `Player` identity in K3. A contact is not resolved or linked by display name/handle.

Alliance intelligence observations do not mutate player snapshots or roster state.

### Transfer planning

Diplomacy/intelligence may be viewed by people making transfer decisions, but `KINGDOMS-003` does not automatically change transfer destination, readiness, groups or completion based on diplomacy state or observed alliance metrics.

### Memberships and Identity

External diplomacy contacts are not application users or memberships. A contact record never grants authentication or authorization.

### Integrations

No public API, webhook, bot or automated game ingestion contract is introduced. Accepted K3 actions/queries preserve first-party business invariants; separately approved future ingestion cannot bypass those invariants merely because K3 exists.

## 8. Delivery slices

### Slice A — External alliance identity and tracking foundation — Validated

- neutral `KingdomAlliance` reference;
- alliance-owned tracking relationship;
- stable-ID-only automatic identity resolution;
- same-current-Kingdom constraint;
- active/archive lifecycle;
- member/manager tracked-alliance views; and
- tenancy/authorization/audit/outbox boundaries.

### Slice B — Observations and historical facts — Validated

- append-oriented alliance observations;
- power/member/name/tag capture;
- source/provenance and actor attribution;
- latest projection and stale/missing state;
- safe correction/invalidation preserving history; and
- retry/idempotency protection for manual submission.

### Slice C1 — Diplomacy and NAP lifecycle — Validated

- explicit diplomacy state;
- effective/review/expiry metadata;
- append-oriented transition history;
- manager-private rationale/terms;
- human-review-on-expiry behavior; and
- no automatic state inference/transition.

### Slice C2 — Diplomacy contacts — Validated

- manager-private contact records;
- role/channel/handle/verification lifecycle;
- tenant isolation and minimum-data rules;
- no player/account/membership/permission linkage; and
- contact assignment never granting authorization.

### Slice D — Intelligence dashboard and derived trends — Validated

- member-safe tracked-alliance intelligence overview;
- manager history/contact detail;
- freshness/data-quality indicators;
- bounded 7/30-day descriptive changes;
- diplomacy review-needed summaries;
- filters/query/index hardening; and
- explicit anti-ranking/anti-threat-score controls.

### Whole-increment hardening — `K3-P6` — Accepted

The final hardening phase validates the complete dependency stack across tenancy, history integrity, privacy, accessibility, K3-only rollback/reapply to K2, realistic-volume query shape, internal webhook boundaries and explicit non-capabilities. Exact evidence is in the [KINGDOMS-003 exit report](kingdoms-alliance-intelligence-exit-report.md).

## 9. Explicitly out of scope

`KINGDOMS-003` does not implement:

- scraping, OCR, browser automation, Discord bots or undocumented/unapproved Kingshot APIs;
- automated game-data ingestion (`KINGDOMS-004` is a separate scope);
- public or cross-alliance visibility of another tenant's observations, contacts, notes or diplomacy state;
- opt-in shared kingdom aggregates/rankings (`KINGDOMS-005` candidate scope);
- automatic alliance ranking, “threat” scoring or desirability scoring;
- battle outcome prediction;
- automated NAP/ally/rival inference from attacks, events or power changes;
- automated diplomacy recommendations or negotiation messages;
- automatic transfer destination/readiness/completion changes;
- public contact directory;
- collection of private credentials, secrets, phone numbers or home addresses for diplomacy contacts;
- platform-Alliance auto-creation or auto-linking from observed game alliances;
- public Kingdoms/diplomacy API or webhook contracts; or
- AI-generated punitive recommendations or automated player/alliance decisions.

Deferred capabilities are not partially introduced as dormant schema, routes or UI placeholders.

## 10. Security, privacy and abuse requirements

Acceptance reviewed:

- cross-alliance tracked-intelligence disclosure;
- object-ID tampering across tracked alliance, observation, diplomacy and contact IDs;
- conflating neutral `KingdomAlliance` with platform `Alliance` authorization;
- tag/name collision and accidental identity merge;
- stale or fabricated power/member observations;
- manager-note, diplomacy-term and contact-handle leakage;
- contact records used as an unauthorized identity path;
- coordinator/contact role confusion with authorization;
- Alliance Kingdom drift and stale tracking context;
- punitive ranking/threat-score abuse;
- automated diplomacy inference accidentally introduced through derived metrics; and
- accidental public API/webhook exposure.

Game-facing information is not automatically public merely because it may be observable in-game. Alliance-owned observations/diplomacy remain tenant-private unless a later approved sharing contract says otherwise.

See the accepted [whole-increment security review](../security/kingdoms-alliance-intelligence-security-review.md).

## 11. Operational and observability requirements

The increment provides enough structured diagnostics to investigate:

- neutral alliance identity resolution failures;
- tracking/current-Kingdom invariant failures;
- observation validation/idempotency failures;
- stale/missing data-quality states;
- diplomacy transition failures;
- contact validation/tenant failures;
- authorization/object-ID failures; and
- outbox publication failures.

Private notes, diplomacy terms and contact handles are not general application logs or durable event payload data.

No recurring crawler, external ingestion worker or diplomacy scheduler is required. Expiry/review-needed status is derived at read time and does not automatically mutate relationship state.

## 12. Testing requirements

Acceptance includes:

- identity/matching tests proving stable game alliance ID is the only automatic match key;
- feature tests for tracking, observations, diplomacy, contacts and intelligence views;
- authorization tests for `alliance.view` and `kingdoms.manage`;
- tenant-isolation tests across submitted object identifiers and shared neutral references;
- same-current-Kingdom and Alliance-Kingdom-drift tests;
- append-history and observation retry/idempotency tests;
- diplomacy transition and expiry/no-auto-transition regression tests;
- private contact/terms/note leakage tests;
- anti-ranking/threat-score architecture tests;
- audit/outbox payload-safety tests;
- accessibility validation for first-party intelligence/diplomacy surfaces;
- migration rollback/reapply validation to the accepted `KINGDOMS-002` baseline; and
- realistic-volume query-shape validation for tracked alliances, observation history and diplomacy summaries.

## 13. Acceptance criteria

All acceptance criteria are satisfied:

1. An Alliance can track a neutral game-side alliance reference in its current Kingdom without creating or granting access to a platform Alliance.
2. Stable game alliance ID is the only automatic identity-match key; tag/name alone never merges identities.
3. Alliance-owned observations preserve history and clearly distinguish missing/stale/current data.
4. Authorized managers can maintain explicit diplomacy/NAP/ally/friendly/rival state with attributable history and no automatic inference.
5. Expiry/review dates surface human review without silently changing diplomacy state.
6. Manager-private contacts support coordination without becoming users, memberships or authorization grants.
7. Ordinary members can see approved safe intelligence/diplomacy information while private notes, terms, handles and privileged history remain protected.
8. Every read/mutation preserves active-Alliance tenancy even when two tenants reference the same Kingdom/game-side alliance.
9. Alliance Kingdom drift fails closed for privileged stale-context mutations rather than silently retargeting intelligence records.
10. Derived intelligence remains descriptive and data-quality aware; no threat score, punitive ranking, automated diplomacy recommendation or automatic transfer behavior is introduced.
11. Privileged changes are password-confirmed, authorized, audited and durably represented through internal outbox events without private payload leakage.
12. No public Kingdoms/diplomacy API or generic external webhook exposure is introduced.
13. Security, accessibility, migration, query-shape, operations and whole-increment acceptance gates passed on the complete stack.
14. Current capability documentation has been promoted to Accepted only after the acceptance gate passed.

Exact validated implementation SHA: `068c4086744f71d33453734f1f1b05fe1430cbff`. Protected Dependency Review `31430279647`, CodeQL `31430279652`, and CI `31430279638` all succeeded; the full suite passed 359 tests / 4,824 assertions.

`KINGDOMS-003` is **Accepted** for repository/product purposes. Real production cutover remains a separate approval decision and is not implied by repository/product acceptance.
