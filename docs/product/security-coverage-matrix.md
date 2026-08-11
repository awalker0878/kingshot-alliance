# Security, privacy, and data-protection coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P2` — Security, privacy, and data-protection completeness  
**Inventory state:** Frozen — implementation in progress

## 1. Purpose

This is the authoritative `DCP-P2` inventory. It records every canonical code domain, the security/privacy/data-protection concerns reviewed, whether a focused living security review is required, the historical/current evidence used to establish the boundary, and the P2 completion state.

A domain is not P2-complete merely because its root contract has a `Security and privacy` section. Every domain must have a current `security/README.md` profile following [Security documentation standard](security-documentation-standard.md).

## 2. Focused-review decision key

- **Profile only** — the mandatory domain security profile can coherently cover the current security boundary; no separate living focused review is required.
- **Focused review** — one or more independently high-risk capabilities require their own living review.
- **Existing review set** — accepted/current domain-owned security reviews already provide the required focused depth and remain indexed by the domain security profile.

## 3. Frozen domain inventory

| Domain | Security/privacy concerns reviewed | P2 decision | Required living focused reviews | Primary source/evidence | P2 status |
| --- | --- | --- | --- | --- | --- |
| Alliances | tenant principal, active-Alliance selection/revalidation, tenant snapshot propagation, Alliance creation/settings, Kingdom association | Focused review | `tenant-context-security-review.md` | Phase 1 threat model; tenant-context contract; shared baseline | In progress |
| Audit | privileged-event attribution, metadata minimization, tenant/actor correlation, retained evidence, secret exclusion | Profile only | None | Phase 1 threat model; Audit contract; shared baseline | In progress |
| Authorization | permission vocabulary, tenant-specific RBAC, role assignment/removal, hierarchy/last-Owner safety, Platform separation | Profile only | None | Phase 1 threat model; Authorization contract; shared baseline | In progress |
| Content | public/member visibility, revision history, authoring privilege, private media, upload/scanner/storage boundary | Focused review | `media-security-review.md` | Phase 2 threat model; Content/media contracts; shared baseline | In progress |
| Contributions | manager/member reporting, subjective/private evidence, corrections/reversals, exports, derived Event facts | Profile only | None | Phase 5 threat model; Contributions contracts; shared baseline | In progress |
| Events | private registration/attendance, authenticated CSV/ICS, recurrence integrity, capacity/waitlist concurrency | Profile only | None | Phase 3 threat model; Events contracts; shared baseline | In progress |
| Identity | global account authentication, password/session assurance, verification, TOTP MFA, recovery-code secrecy/replay | Focused review | `mfa-and-recovery-security-review.md` | Phase 1 threat model; Identity/MFA contracts; shared baseline | In progress |
| Integrations | Alliance-bound API credentials/scopes, external read API, webhook signing/delivery, endpoint/egress safety | Focused review | `api-security-review.md`, `webhooks-security-review.md` | Phase 6 threat model; Integrations capability contracts; shared baseline | In progress |
| Kingdoms | neutral game identity, roster/snapshots/import, transfer planning, private diplomacy/contact data, descriptive intelligence | Existing review set | Existing Kingdoms security reviews indexed by `security/README.md` | K1–K3 security reviews; Kingdoms contracts; shared baseline | In progress |
| Memberships | membership lifecycle/hierarchy, role strip/restore, bearer invitation issue/revoke/resend/acceptance, Recruitment handoff | Focused review | `invitations-security-review.md` | Phase 1 threat model; Memberships/invitations contracts; shared baseline | In progress |
| Notifications | tenant/member coordination state, Event reminder eligibility, scheduled report identities, outbox payload minimization | Profile only | None | Phase 3/5/6 evidence; Notifications contracts; shared baseline | In progress |
| Platform | cross-tenant administrator grant, MFA/password assurance, legal hold, deletion/restoration/anonymization/export, shared outbox | Focused review | `lifecycle-and-retention-security-review.md`, `transactional-outbox-security-review.md` | Phase 6 threat model; Platform capability contracts; shared baseline | In progress |
| Rallies | Alliance-private guidance/formations/groups/assignments/participation, active-member assignment, no game automation | Profile only | None | Phase 3 threat model; Rallies contract; shared baseline | In progress |
| Recruitment | anonymous/invitation-only intake, candidate PII/private notes, duplicate merge, recruiter access, retention/anonymization | Focused review | `application-intake-security-review.md` | Phase 4 threat model; Recruitment/intake contracts; shared baseline | In progress |

## 4. Focused review rationale

### Alliances — tenant context

Active Alliance selection/revalidation and `TenantContextSnapshot` establish the application-wide tenant boundary used by requests and propagated into jobs/cache/storage/export/log contexts. A failure here can create cross-tenant disclosure or authorization bypass across otherwise-correct feature domains.

### Content — media

Uploaded bytes cross an untrusted-input boundary into private tenant storage and later may be presented publicly as branding. Validation, screening, object ownership, tenant-prefixed storage, public eligibility, and production malware-scanner dependency form an independent security lifecycle.

### Identity — MFA and recovery

MFA enrollment/challenge and recovery-code consumption own encrypted/hashed authentication material and one-time replay semantics. They materially strengthen privileged access and require explicit downgrade/reuse/session controls.

### Integrations — API and webhooks

The API and webhook capabilities are independently externally observable machine trust boundaries. API credentials/scopes/tenant derivation differ materially from webhook endpoint validation, signing, payload eligibility, delivery identity, retries, and production egress controls.

### Memberships — invitations

Invitation links are expiring bearer access material. Token hashing, email binding, one-time acceptance, replacement/revocation, concurrency, and Recruitment handoff require a focused review separate from ordinary membership-state administration.

### Platform — lifecycle/retention and transactional outbox

Platform lifecycle can perform high-impact cross-tenant hold/export/deletion/restoration/anonymization operations and therefore requires its own destructive-operation/privacy review. The transactional outbox is shared infrastructure whose payload minimization, at-least-once semantics, retry behavior, and external-non-contract boundary can affect every producer domain.

### Recruitment — application intake

Application intake is anonymously or invitation-access reachable and accepts personal applicant data before a normal Alliance membership exists. Public/private separation, access links, throttling, question/answer handling, candidate creation, and later retention/anonymization make it an independent privacy/security boundary.

## 5. Profile-only rationale

### Audit

Audit has one coherent evidence capability. Its profile can completely cover least-privilege access, metadata minimization, secret exclusion, tenant/actor attribution, retention/redaction, and the distinction between audit evidence and business/outbox state.

### Authorization

The current authorization model is a fixed permission vocabulary plus built-in role templates and role assignment/removal. It has no custom-role editor or separate external boundary; a complete domain profile is sufficient.

### Contributions

Current security risk is primarily privileged tenant-private reporting/export, explainable subjective/private evidence, and integrity of correction/reversal/provenance. The existing domain/capability contracts are coherent enough for one complete security profile.

### Events

Registration/attendance concurrency is security-relevant integrity behavior but does not introduce secret material or an anonymous/external trust boundary. Authenticated CSV/ICS remain tenant-bound. One profile can cover the current implemented surface.

### Notifications

Notifications stores coordination state and source-derived payloads but does not own generic external transport, credentials, or public endpoints. One profile can cover tenant identity, payload minimization, source eligibility, deterministic identity, and outbox safety.

### Rallies

Rallies is an authenticated Alliance-private coordination feature with no accepted public machine boundary or automated game execution. Its profile can cover identifier re-resolution, active-member assignment, manager authorization, private member state, and explicit non-automation.

## 6. Existing Kingdoms review set

Kingdoms already has domain-owned reviews for foundation, roster, snapshots, CSV migration, descriptive intelligence, transfer planning/completion, game-Alliance tracking/observations/diplomacy/contacts/dashboard, and whole-increment security acceptance.

P2 does not cosmetically rewrite those accepted reviews. It normalizes `docs/domains/kingdoms/security/README.md` into the mandatory living profile and retains the accepted review set as indexed historical/current domain evidence.

## 7. Shared baseline reconciliation

Every domain profile must explicitly reconcile its local controls with:

- [`docs/security/security-baseline.md`](../security/security-baseline.md);
- the owning domain contract/capability documents; and
- any historical phase or increment security evidence used as a source.

A historical statement that has been superseded by current code/docs is treated as history, not current authority.

## 8. P2 exit checklist

- [ ] 14/14 domain security profiles exist and satisfy `security-documentation-standard.md`.
- [ ] All 9 new required focused living security reviews exist and satisfy the focused-review format.
- [ ] Kingdoms security profile is normalized and indexes its existing domain review set.
- [ ] Shared security baseline and living domain security documentation agree.
- [ ] All applicable secret/credential, privacy, retention/deletion/anonymization, trust-boundary, abuse, destructive-operation, and residual-risk requirements are explicit.
- [ ] Domain security navigation is complete and local links resolve.
- [ ] P2 structural/metadata/heading/frozen-inventory CI is active.
- [ ] Protected validation passes on the exact P2 content candidate head.
- [ ] P2 exit/status evidence is finalized and the final evidence head also passes protected validation.

The phase remains **In progress** until every item above is complete.