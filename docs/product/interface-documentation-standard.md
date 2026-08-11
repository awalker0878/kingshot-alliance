# Interface, event, and integration documentation standard

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** Normative documentation standard  
**Status:** Current  
**Primary phase:** `DCP-P4` — Interfaces, events, and integrations completeness

## 1. Purpose

This standard defines how the repository documents material boundaries into, out of, and between code domains. It covers HTTP/UI/API surfaces, internal actions/queries/services, domain/application events, transactional-outbox contracts, commands/jobs/scheduled work, imports/exports/media boundaries, and external integrations.

The goal is **contract-level discoverability**. Documentation must tell a maintainer or integrator what the boundary is, who owns it, who may call it, what enters/leaves it, how it fails, and what compatibility guarantees exist. It must not become a generated endpoint dump or one Markdown file per controller/action/job.

## 2. Source-of-truth precedence

When interface documentation and implementation disagree, resolve the defect using this order:

1. executable route/bootstrap/scheduler/provider configuration and runtime code;
2. tests that exercise the current contract;
3. the owning domain's living interface profile and focused interface contracts;
4. the owning domain contract/capability documentation;
5. shared product/security/operations documentation; and
6. historical phase/increment evidence.

Accepted historical evidence records what was validated at an accepted SHA. It does not override later current runtime code.

## 3. Ownership model

Every canonical code domain has exactly one living interface profile:

```text
app/Domain/<Domain>/
docs/domains/<domain>/interfaces/README.md
```

The profile is the deterministic interface inventory and navigation point for that domain. It owns the domain's present-tense boundary description even when the physical HTTP route is declared in a shared route file or a first-party controller coordinates more than one domain.

A focused interface contract belongs under:

```text
docs/domains/<domain>/interfaces/<capability>.md
```

unless an already accepted living capability document fully owns that boundary. Existing complete capability contracts are reused and linked rather than duplicated only to satisfy P4 formatting.

## 4. What counts as a material interface

A boundary is material when one or more of these are true:

- it is externally observable over HTTP, files, mail/webhooks, machine credentials, or another network boundary;
- it has public/member/manager/platform-administrator authorization semantics;
- it passes control or data between code domains through a supported action/query/service/event contract;
- it is asynchronous, retryable, scheduled, or queue-backed;
- it has a stable or security-sensitive token, signature, schema, file format, header, scope, or version contract;
- it performs privileged import/export/disclosure;
- another domain or external consumer relies on its error/idempotency/compatibility behavior; or
- the absence of a capability is itself a significant safety/product boundary.

Private implementation helpers that do not form a supported caller boundary do not need independent documentation.

## 5. Route and UI documentation rule

Routes are inventoried by **contract family**, not copied line-by-line into documentation. A domain profile must identify its route/workspace families, their caller classes, authorization/tenancy requirements, material inputs/outputs, and ownership.

Exact paths are included when they are externally significant, security-sensitive, format-bearing, or necessary to disambiguate the contract. Runtime route files remain authoritative for exhaustive endpoint enumeration.

UI/Inertia pages are documented as caller-facing workspaces or entry points. P4 does not require one document per Vue page.

## 6. Internal actions, queries and services

A supported internal action/query/service contract must identify:

- owning domain;
- intended caller domains or first-party adapters;
- authorization/tenant preconditions that the caller must preserve;
- material input/output semantics;
- mutation versus read behavior;
- failure/idempotency/concurrency behavior when material; and
- whether the contract is stable for cross-domain use or merely private implementation detail.

Direct cross-domain persistence reach-through is not legitimized by documenting it. Existing domain-boundary rules remain normative.

## 7. Events and transactional outbox

For each material event family, documentation must distinguish:

- **producer-owned event meaning** — the domain that owns the business transition;
- **Platform outbox infrastructure** — durable recording/publication mechanics;
- **internal consumers** — listeners/actions that react after publication; and
- **external eligibility** — whether Integrations exposes the event to webhooks.

An internal outbox event is **not** automatically a public webhook contract. External eligibility must be explicit. Event payloads must document safe identifiers/data at contract level without treating private persistence snapshots as public schemas.

At-least-once publication, idempotency identity, replay/reconciliation, and failure behavior must be linked to the owning Platform/operations contracts where applicable.

## 8. Commands, jobs and scheduled work

Every custom command or material scheduled workflow must be discoverable from the owning domain profile or a focused contract. Documentation must identify:

- command/job purpose and owner;
- caller (operator, scheduler, outbox listener, or application action);
- bounded options/limits where compatibility or safety depends on them;
- queue name where applicable;
- retry/idempotency/recovery behavior; and
- the durable state that determines safe catch-up.

Shared framework maintenance commands need only be documented at the shared operations level unless a domain contract depends on them directly.

## 9. API and machine-credential contracts

Externally callable APIs require explicit documentation of:

- route/version family;
- authentication mechanism and credential format at a safe contract level;
- fixed scopes/permissions;
- tenant derivation;
- rate and row bounds;
- response envelope/fields at compatibility-relevant depth;
- status/error behavior;
- expiry/revocation and secret handling;
- versioning/compatibility policy; and
- explicit write/non-capability boundaries.

Machine API documentation is Integrations-owned even when the represented facts are owned by Alliances, Events, Contributions, or another feature domain.

## 10. Webhook contracts

Outbound webhook documentation must define:

- subscription management/eligibility;
- externally eligible versus internal-only event families;
- envelope fields and payload-size bound;
- delivery identity/idempotency;
- signature algorithm/input and required headers;
- endpoint policy/revalidation;
- timeout/retry/backoff behavior;
- terminal failure behavior; and
- compatibility/non-capability boundaries.

Wildcard subscription matching never broadens external eligibility beyond the explicitly accepted event boundary.

## 11. Files, imports, exports and media

Material file boundaries must document as applicable:

- exact or versioned schema/header set;
- MIME/content-disposition behavior;
- size/row limits;
- text encoding and parser constraints;
- authorization/disclosure class;
- dry-run/preview/commit semantics;
- checksum/provenance/evidence headers;
- formula/script/HTML/media safety constraints;
- compatibility/version behavior; and
- whether the file is an interchange contract, operator artifact, or first-party convenience export.

A filename extension must not be used to imply a format different from the implemented bytes. For example, SpreadsheetML XML served as `.xls` must be documented as SpreadsheetML rather than OOXML `.xlsx`.

## 12. External dependencies

A domain profile must identify externally relevant dependencies such as mail, DNS/HTTP egress, object storage, media scanning, Redis/queues, PostgreSQL, or framework session/authentication where they affect the boundary contract.

Operational recovery detail remains in P3 operations documentation. P4 records how dependency degradation appears at the interface and what caller-visible contract is preserved or fails closed.

## 13. Failure, idempotency and compatibility

Interface documentation must distinguish:

- validation (`4xx`) from authorization/tenant failure and server/dependency failure;
- safe retry from non-idempotent user intent;
- durable event/message identity from business mutation identity;
- request replay from asynchronous catch-up/reconciliation;
- explicit versioned schemas from unversioned-but-stable compatibility contracts; and
- additive compatible change from a breaking change that requires documentation/tests/version review.

Do not promise compatibility that runtime code/tests do not enforce.

## 14. Public versus internal vocabulary

Use these terms consistently:

- **Public/anonymous** — callable without authenticated application identity.
- **First-party member** — authenticated/verified active-Alliance member surface.
- **First-party manager** — authenticated Alliance management surface with owning permission and required assurance.
- **Platform administrator** — cross-tenant administrative surface protected by Platform grant plus Identity assurance.
- **External machine API** — bearer-credential machine contract under Integrations.
- **External webhook** — outbound HTTPS contract under Integrations.
- **Internal cross-domain contract** — supported in-process action/query/service/event consumed by another domain.
- **Private implementation** — not a supported caller contract.

## 15. Living domain interface profile format

Every `docs/domains/<domain>/interfaces/README.md` uses the following metadata:

```markdown
**Document type:** Living domain interface profile
**Status:** Current
**Owning domain:** <CanonicalDomain>
**Code owner:** `app/Domain/<CanonicalDomain>`
**Primary boundary:** <concise boundary statement>
**P4 inventory decision:** <profile-only / focused contracts reused / focused contract added>
```

Required section order:

1. `## 1. Boundary purpose and ownership`
2. `## 2. Surface inventory`
3. `## 3. Callers, authorization and tenancy`
4. `## 4. Input and validation contracts`
5. `## 5. Output and disclosure contracts`
6. `## 6. Internal actions, queries and services`
7. `## 7. Events, outbox and cross-domain consumers`
8. `## 8. Commands, jobs and scheduled work`
9. `## 9. Files, imports, exports and external dependencies`
10. `## 10. Failure, idempotency, versioning and compatibility`
11. `## 11. Explicit non-capabilities`
12. `## 12. Focused contracts, evidence and related documentation`

Profiles summarize families and ownership. They do not reproduce every controller method or payload field when a focused/accepted capability contract owns those details.

## 16. New focused interface contract format

New P4 focused interface contracts use this metadata:

```markdown
**Document type:** Living focused interface contract
**Status:** Current
**Owning domain:** <CanonicalDomain>
**Capability:** <boundary/capability>
**Code owner:** `app/Domain/<CanonicalDomain>`
```

Required section order:

1. `## 1. Contract scope and owner`
2. `## 2. Entry points and caller classes`
3. `## 3. Authorization, tenancy and rate limits`
4. `## 4. Request and input format`
5. `## 5. Response and output format`
6. `## 6. State changes, events and asynchronous behavior`
7. `## 7. Failure, idempotency and retry`
8. `## 8. Versioning and compatibility`
9. `## 9. Security, privacy and operational constraints`
10. `## 10. Tests, non-capabilities and related documentation`

Accepted P1 capability documents reused by P4 retain their accepted format; P4 profiles must link them explicitly.

## 17. P4 frozen-inventory rule

`interface-coverage-matrix.md` is the authoritative P4 inventory. Before P4 can be Candidate it must identify:

- all canonical domains;
- every executable repository route file plus bootstrap-mounted route/health surfaces;
- material external API/webhook contracts;
- material custom commands/jobs/scheduled work;
- material event/outbox producer/consumer boundaries;
- material file/import/export/media contracts;
- focused contracts required or deliberately reused; and
- explicit significant non-capabilities.

Newly discovered material scope remains P4 work until the matrix is updated and the boundary is fully documented.

## 18. P4 CI enforcement

P4 CI should enforce high-signal structural claims, including:

- one current interface profile per canonical code domain;
- required metadata/section ordering;
- frozen new-focused-contract inventory;
- links to reused accepted capability contracts;
- executable route-file coverage in the matrix;
- owning-domain navigation; and
- repository-wide local Markdown-link integrity.

CI must not attempt brittle semantic parsing of every route/controller payload. Runtime tests remain responsible for executable contract behavior; P4 architecture tests protect discoverability/ownership/required documentation structure.

## 19. P4 completion gate

P4 is Complete only when:

- 14/14 domain interface profiles are current;
- every material interface/integration in the frozen matrix has an owner and complete contract documentation;
- required new focused contracts exist and reused focused contracts are indexed;
- public/member/manager/platform-admin/external-machine/internal boundaries are explicit;
- producer/consumer and outbox/external-eligibility ownership agrees across domains;
- externally observable file/API/webhook behavior is documented at compatibility-relevant depth;
- explicit non-capabilities are preserved;
- P4 architecture and repository Markdown-link validation pass; and
- exact candidate and final evidence/status heads pass protected Dependency Review, CodeQL, and complete CI.

P5 cannot begin before this gate closes.
