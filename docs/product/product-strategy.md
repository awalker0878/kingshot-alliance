# Kingshot Alliance product strategy

[← Product and program documentation](README.md)

**Status:** Proposed strategy  
**Applies after:** `KINGDOMS-003` repository/product acceptance  
**Baseline:** Completed Phase 0–6 program plus accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` increments  
**Production status:** Real production cutover remains separately **not yet approved**

## 1. Purpose

This document defines the product direction after the accepted Kingdoms foundation, roster intelligence, transfer planning, and alliance intelligence/diplomacy increments.

It is a strategy and prioritization record, not an implementation approval. It does not make any future increment **Approved**, **In progress**, or **Accepted**. Future work still requires its own named scope, implementation plan when appropriate, security/operations review, protected validation, and exit record.

The strategy exists to prevent two failure modes:

1. continuing to add isolated features without a coherent product outcome; and
2. treating technically possible automation, sharing, scoring, or integration work as implicitly approved.

## 2. Strategic position

Kingshot Alliance should evolve as a **human-directed alliance operations platform** rather than a game bot, autonomous decision engine, or opaque scoring system.

The platform's strongest differentiated capability is the combination of:

- alliance-scoped operational workflows;
- first-class Kingdom, player, roster, transfer, and game-alliance reference models;
- attributable factual history;
- explicit human decisions for sensitive state changes;
- tenant isolation and manager-private information boundaries;
- descriptive intelligence derived from evidence rather than hidden scoring; and
- durable audit/outbox, recovery, accessibility, and production-oriented engineering controls.

The next product investments should deepen that operating model before introducing broader automation.

## 3. Product north star

**Give alliance leadership one trustworthy control plane for understanding their alliance, coordinating people, planning transfers and diplomacy, and acting on current information without surrendering human judgment or tenant privacy.**

A successful product should reduce the amount of leadership work performed through fragmented chat threads, screenshots, spreadsheets, manual reminders, and undocumented personal knowledge.

The platform should make important operational state:

- structured;
- attributable;
- current or explicitly stale;
- easy to review;
- safe to correct;
- visible only to the right audience; and
- actionable through explicit human-controlled workflows.

## 4. Strategic principles

### 4.1 Human authority remains explicit

Facts may be imported or derived when their provenance is trustworthy, but sensitive decisions remain deliberate human actions unless a future scope explicitly proves a safer model.

The platform must not silently infer or execute:

- diplomacy transitions;
- transfer completion;
- member removal or punishment;
- player desirability;
- threat or rival scoring;
- recruitment acceptance/rejection;
- target selection; or
- other consequential alliance decisions.

Automation may prepare information, detect stale/missing data, suggest review, or reduce mechanical data entry. It must not quietly become decision authority.

### 4.2 Tenant-owned intelligence stays tenant-owned by default

Shared neutral references such as `Kingdom`, `KingdomPlayer`, and `KingdomAlliance` are identity/reference infrastructure, not permission bridges.

Alliance-owned roster state, observations, transfer plans, diplomacy history, contacts, notes, corrections, provenance, and derived summaries remain private to the owning Alliance unless a future opt-in sharing scope explicitly defines otherwise.

### 4.3 Provenance is more valuable than volume

More data is not automatically better data.

Any future ingestion path should preserve:

- source;
- capture time;
- actor or system provenance;
- confidence/verification state where applicable;
- correction/invalidation history;
- idempotency; and
- clear missing/stale semantics.

The platform should prefer a smaller trustworthy dataset over a larger opaque one.

### 4.4 Descriptive intelligence before predictive intelligence

The current model intentionally favors factual totals, bounded historical trends, freshness, change, and review-needed indicators.

Future analytics should continue to answer questions such as:

- What changed?
- When was it last verified?
- Which facts are stale or missing?
- Which plans or relationships need attention?
- What evidence supports this view?

Predictive, punitive, desirability, threat, or "best/worst" scoring remains outside the strategy unless separately proposed and justified with explicit governance, explainability, fairness, abuse, and privacy controls.

### 4.5 Integrate through approved contracts, not fragile shortcuts

The product should not depend on scraping, OCR pipelines, bots, undocumented game APIs, credential capture, or reverse-engineered automation merely because those techniques are technically possible.

Future ingestion and integration should prefer, in order:

1. documented and permitted first-party interfaces;
2. explicit user-supplied structured imports;
3. administrator-controlled integration adapters with stable contracts; and
4. manual entry when no trustworthy automation path exists.

### 4.6 Operational maturity is a product feature

Production launch, observability, recovery, accessibility, security, privacy, and maintainability are not secondary to feature delivery.

A roadmap item that materially increases operational risk must carry its own runbooks, telemetry expectations, failure modes, rollback/recovery behavior, capacity assumptions, and security review.

## 5. Strategic horizons

The horizons below express sequencing intent. They do not approve the named candidate increments.

### Horizon A — Production confidence and product coherence

**Outcome:** Make the accepted product easier to operate, understand, and eventually launch in a real hosted environment.

Priorities:

- complete the external infrastructure and accountable evidence required by `production-launch-approval.md`;
- validate hosted configuration, secrets, TLS/proxy assumptions, queues/scheduler ownership, backups, restore, monitoring, alerting, and incident ownership;
- close documentation drift so `/docs` reflects `KINGDOMS-001` through `KINGDOMS-003` as the accepted current baseline;
- improve navigation between member, leader, Kingdoms, transfer, diplomacy, event, recruitment, and contribution workflows;
- consolidate duplicated dashboard information rather than creating parallel sources of truth;
- identify the highest-friction recurring leadership tasks using the accepted workflows before approving new automation.

This horizon may proceed in parallel with product-scope design, but repository/product acceptance must not be described as real production approval.

### Horizon B — Trusted data acquisition

**Candidate direction:** `KINGDOMS-004` — controlled game-data ingestion.

**Outcome:** Reduce repeated manual factual entry without weakening provenance, correction history, tenant isolation, or source trust.

A future `KINGDOMS-004` proposal should answer these questions before approval:

1. What approved data source or user-controlled import mechanism exists?
2. What exact entities/facts may be ingested?
3. How is provenance represented and surfaced?
4. How are duplicate deliveries made idempotent?
5. How are conflicting, stale, future-dated, or malformed observations handled?
6. Which facts may update neutral reference identity, and which remain tenant-owned observations?
7. How can an operator disable, replay, or recover ingestion safely?
8. What rate, queue, retry, back-pressure, and observability controls are required?
9. How are private credentials/secrets isolated and rotated?
10. How is the existing manual correction/invalidation model preserved?

Initial scope should favor factual ingestion into already-accepted models rather than creating a second intelligence model.

Explicitly out of scope by default:

- automated diplomacy changes;
- automated transfer decisions/completion;
- scoring/ranking;
- scraping/OCR/bot-based collection without a separately approved contract;
- credential capture for game accounts;
- cross-tenant data publication; and
- public Kingdoms API/webhook exposure simply as a side effect of ingestion.

### Horizon C — Opt-in Kingdom collaboration

**Candidate direction:** `KINGDOMS-005` — shared/opt-in Kingdom intelligence.

**Outcome:** Allow alliances to deliberately collaborate on selected factual intelligence while preserving tenant ownership, revocation, attribution, and private-data boundaries.

A future sharing scope should begin from **explicit publication**, not implicit visibility of existing tenant records.

Required design principles:

- sharing is opt-in and revocable;
- the publishing Alliance chooses what is shareable;
- receiving Alliances can distinguish local facts from shared facts;
- source Alliance and provenance remain attributable;
- manager-private notes, diplomacy terms/rationale, contact handles/notes, internal actor metadata, transfer plans, recruitment data, contribution data, and other private tenant state are excluded by default;
- shared facts cannot grant membership, permissions, identity linkage, or mutation authority;
- conflict between local and shared observations does not silently overwrite either history;
- retention after revocation is explicitly defined;
- abuse, harassment, intelligence-poisoning, and cross-tenant inference risks are threat-modeled before implementation.

The first shared capability should be narrow and factual. A kingdom-wide social network, public player database, global ranking system, or public contact directory is not implied.

### Horizon D — Leadership workflow acceleration

**Candidate direction:** cross-domain operations workspace after trusted data and sharing boundaries are proven.

**Outcome:** Reduce context switching by presenting the most important current operational work across accepted domains without creating new hidden decision logic.

Potential capabilities for later scoped evaluation:

- upcoming events and rally readiness;
- recruitment items needing review;
- contribution/reporting exceptions;
- stale/missing roster or alliance intelligence;
- transfer blockers and incomplete handoffs;
- diplomacy relationships due for human review;
- recent material changes with provenance; and
- operator-visible system health relevant to alliance workflows.

This should be a projection/navigation layer over authoritative domain state, not a new persistence model that duplicates business truth.

### Horizon E — Selective integrations and notifications

**Outcome:** Deliver information where leaders already work while keeping the platform authoritative and avoiding unsafe two-way automation.

Candidate evaluation areas:

- richer notification transports;
- Discord integration through documented/approved interfaces;
- expanded read-only API contracts where a real consumer exists;
- deliberately versioned external webhook event schemas; and
- import/export adapters for approved structured workflows.

External contracts should be introduced only when there is a defined consumer, authorization model, versioning policy, rate model, privacy boundary, and operational ownership.

## 6. Recommended sequencing

Use the following order unless a documented product decision changes it:

1. **Maintain the accepted baseline** — fix correctness, security, accessibility, documentation drift, and operational defects first.
2. **Advance production readiness independently** — collect external evidence required for real cutover; do not couple production approval to feature expansion.
3. **Design trusted ingestion (`KINGDOMS-004`)** — only after identifying an approved, supportable data source or controlled import contract.
4. **Prove ingestion in narrow factual slices** — reuse current roster/snapshot/alliance-observation contracts and preserve manual correction/history semantics.
5. **Design opt-in sharing (`KINGDOMS-005`)** — only after local provenance and ingestion boundaries are stable.
6. **Add cross-domain workflow acceleration** — build leadership summaries from accepted domain projections rather than new scoring systems.
7. **Expand integrations selectively** — expose only the contracts demanded by real consumers and supported operationally.

Do not start multiple new data-governance models in parallel. Ingestion should establish trustworthy provenance before sharing multiplies the distribution of that data.

## 7. Candidate increment model

Future material product work should continue the named-increment model already used by the Kingdoms program.

A candidate becomes an approved increment only when its scope record defines:

- stable scope ID and outcome;
- owner and accountable acceptance authority;
- user/business problem;
- dependencies on accepted capabilities;
- domain ownership and data model boundaries;
- tenant/privacy model;
- authorization and privileged-action requirements;
- integration/event/API boundaries;
- migration/rollback implications;
- observability and operational requirements;
- accessibility requirements;
- abuse/security threat model;
- measurable acceptance criteria;
- explicit deferrals and non-goals; and
- production-launch implications.

Large increments should use a gated plan consistent with the existing pattern:

- `P0` — lock cross-cutting decisions and contracts;
- vertical implementation slices with exact scope boundaries;
- protected validation for each meaningful slice;
- final whole-increment hardening/acceptance (`P6` or an equivalent final gate); and
- an exit report tied to exact validated implementation evidence.

The numeric `P` labels belong to the increment and do not reopen the historical Phase 0–6 program.

## 8. Decision framework for approving new work

Before approving a new increment, score the proposal qualitatively against these questions.

### User value

- Does it remove a recurring real leadership/member burden?
- Is the workflow currently fragmented, error-prone, or difficult to audit?
- Does the platform have a meaningful advantage over a spreadsheet/chat-only solution?

### Data trust

- Is the source known and permitted?
- Can provenance and correction history be preserved?
- Can missing, stale, conflicting, and invalid data remain explicit?

### Safety and privacy

- Does it preserve tenant ownership and least privilege?
- Could it create harassment, profiling, punitive scoring, or cross-tenant inference risks?
- Are private notes, contacts, credentials, or operational secrets excluded from inappropriate surfaces/events?

### Human control

- Is the system informing a human or quietly making a consequential decision?
- Is every state-changing automation explicit, reversible where appropriate, and attributable?

### Architecture

- Does it extend an owning domain rather than create duplicate truth?
- Can it use existing actions/queries/events instead of parallel mutation paths?
- Is the future extraction/integration boundary clear if scale later requires it?

### Operations

- Can it be monitored, rate-limited, disabled, retried, recovered, and rolled back?
- Are capacity and failure behavior bounded?
- Can operators distinguish dependency failure from application failure?

### Evidence

- Can acceptance be proven with automated tests plus concrete security, accessibility, migration, query/performance, staging, and recovery evidence?

A proposal with high novelty but weak answers in data trust, tenant safety, human control, or operations should not be prioritized merely because it is technically interesting.

## 9. Measures of strategic progress

Avoid measuring progress primarily by feature count.

Prefer evidence that the platform is becoming more useful and trustworthy:

- fewer repeated manual data-entry steps for the same facts;
- lower stale/missing-data rates where trusted acquisition exists;
- shorter time to identify transfer blockers or diplomacy items needing review;
- fewer duplicated spreadsheet/chat workflows for accepted product capabilities;
- high completion rates for core leader workflows without support intervention;
- bounded query/runtime behavior at realistic alliance volumes;
- no cross-tenant privacy regressions;
- no undocumented external integration contracts;
- successful recovery/rollback exercises after material infrastructure changes; and
- accessible completion of core workflows by keyboard and assistive technology.

Product analytics or telemetry used for these measures must itself follow the privacy and tenant model.

## 10. Explicit non-strategy

This strategy does **not** authorize or imply:

- a new numbered Phase 7;
- `KINGDOMS-004` or `KINGDOMS-005` implementation approval;
- scraping, OCR, bots, or undocumented game APIs;
- game-account credential storage;
- autonomous diplomacy or transfer execution;
- threat, punitive, desirability, or player/alliance ranking scores;
- AI-generated consequential decisions without human review;
- cross-alliance exposure of private tenant records;
- a public Kingdoms API or public Kingdoms webhook catalog;
- payment processing or marketplace functionality; or
- real production launch approval.

Each of those requires a separate explicit product/architecture/security decision if it is ever proposed.

## 11. Documentation impact

This strategy should sit above individual future increment scopes and below the completed baseline implementation plan in planning precedence:

1. `implementation-plan.md` remains the authoritative completed Phase 0–6 baseline.
2. `product-strategy.md` defines post-`KINGDOMS-003` strategic direction and prioritization principles.
3. Approved named increment scopes define the actual authorized product work.
4. Increment implementation plans define delivery sequencing.
5. The current capability matrix defines what is implemented now.
6. Domain, security, operations, ADR, validation, and exit records define detailed contracts/evidence.

When a future increment is approved, update this strategy only if the strategic direction changes. Do not rewrite strategy merely to mirror implementation status; status belongs in the capability matrix, increment records, and indexes.

## 12. Immediate next decisions

Before starting the next runtime increment, the repository should make three explicit decisions:

1. **Production path:** identify the remaining external evidence and accountable owner required to move real launch from Not yet approved to Approved.
2. **Ingestion feasibility:** identify whether a documented/permitted Kingshot data source or sufficiently controlled structured import path exists for a narrow `KINGDOMS-004` proposal.
3. **Leadership priority:** validate which recurring alliance-leadership burden should be reduced next using the accepted K1–K3 capabilities rather than assuming more automation is automatically the highest-value work.

Until those decisions are made, maintenance, documentation alignment, security/accessibility fixes, and production-readiness evidence are valid work; speculative automation should remain unapproved.
