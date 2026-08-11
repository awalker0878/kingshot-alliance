# Security documentation standard

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** Normative documentation standard  
**Status:** Current  
**Primary phase:** `DCP-P2` — Security, privacy, and data-protection completeness  
**Applies to:** Shared security policy, every canonical code domain, material capability security reviews, privacy/data-protection boundaries, and P2 security-documentation CI

## 1. Purpose

This standard defines the repository's living security/privacy documentation model. It makes current security ownership, trust boundaries, sensitive-data handling, abuse controls, destructive operations, residual risk, and verification discoverable from the code domain that owns the behavior.

Historical phase threat models remain evidence of when controls were introduced. They are not substitutes for current domain-owned security documentation.

## 2. Source-of-truth hierarchy

Security documentation follows this authority order:

1. **Implemented code, database constraints, policies/middleware, configuration validation, and tests** — executable behavior.
2. **`docs/security/security-baseline.md`** — current shared/cross-domain security policy and production evidence boundary.
3. **`docs/domains/<domain>/security/README.md`** — living security/privacy profile for one code domain.
4. **`docs/domains/<domain>/security/<capability>-security-review.md`** — focused living review for a material independently risky capability.
5. **Historical threat models/accepted increment security reviews** — immutable evidence and design history; useful context but not the current contract when behavior has evolved.

A lower-precedence document must not redefine behavior owned by a higher-precedence source.

## 3. Ownership and placement

Every canonical `app/Domain/<Domain>/` must have exactly one living domain security profile at:

```text
docs/domains/<domain>/security/README.md
```

Domain-specific security implementation detail belongs there or in focused reviews beneath the same directory.

Top-level `docs/security/` owns only cross-domain/shared security policy, historical phase-wide threat evidence, and production-wide security/go-live boundaries. It must not become a flat collection of domain security reviews.

## 4. Mandatory domain security profile

Every domain security profile is a living document with this metadata:

```text
**Document type:** Living domain security profile
**Status:** Current
**Owning domain:** <Domain>
**Code owner:** `app/Domain/<Domain>`
**Primary security boundary:** <concise boundary>
```

Every profile must contain these sections, in order:

1. `## 1. Security purpose and scope`
2. `## 2. Assets and sensitive data`
3. `## 3. Actors, authentication and authorization`
4. `## 4. Tenant and privacy boundaries`
5. `## 5. Trust boundaries and data flows`
6. `## 6. Threats, abuse cases and controls`
7. `## 7. Integrity, concurrency and idempotency`
8. `## 8. Secrets and credential handling`
9. `## 9. Destructive operations, retention and deletion`
10. `## 10. Auditability, observability and evidence`
11. `## 11. Residual risks and explicit non-capabilities`
12. `## 12. Focused reviews and related documentation`

A section may say **Not applicable** only with a concrete reason. Empty headings, generic boilerplate, `TODO`, or references that force the reader to reconstruct the boundary from another document do not satisfy P2.

## 5. Asset and data classification expectations

Profiles identify the domain's security-relevant assets and classify them by handling need rather than inventing legal labels that the application does not implement.

At minimum, distinguish where applicable:

- public presentation data;
- authenticated member/tenant-private data;
- manager/private operational data;
- personal/account/applicant data;
- security secrets or bearer material;
- audit/history evidence;
- generated exports/files/media;
- cross-tenant administrative data; and
- externally transmitted payloads.

The owning domain states purpose, allowed audience, persistence owner, and lifecycle implications for sensitive classes.

## 6. Authentication, authorization and tenant isolation

Security documentation must distinguish:

- global User authentication from Alliance membership;
- active Alliance context from permission evaluation;
- role/permission authorization from recent password confirmation or MFA assurance;
- Alliance-scoped authority from Platform cross-tenant administration; and
- game/reference identity from application authorization.

Submitted tenant-owned identifiers are re-resolved beneath the authorized tenant. Global/shared reference IDs never grant tenant access.

## 7. Trust boundaries

Every profile identifies material crossings such as:

- anonymous browser → public application surface;
- authenticated browser → tenant-scoped surface;
- privileged browser → manager/platform mutation;
- application → PostgreSQL/Redis/object storage;
- request transaction → scheduler/queue/outbox;
- application → external webhook/API/mail/storage/scanner service; and
- Platform administrator → cross-tenant orchestration.

Only boundaries that apply to the domain are required, but each applicable boundary must state what data/authority crosses it and the relevant control.

## 8. Threat and abuse coverage

Profiles and focused reviews cover applicable risks including:

- cross-tenant IDOR/identifier substitution;
- privilege escalation or stale authorization;
- authentication/recovery bypass;
- secret/token theft, replay or leakage;
- public/private visibility mistakes;
- untrusted upload/input handling;
- SSRF/egress or outbound destination abuse;
- webhook/API replay, scope expansion or retry amplification;
- duplicate/concurrent lifecycle transitions;
- destructive deletion/retention bypass;
- audit/log/export disclosure;
- applicant/member privacy abuse;
- integrity manipulation of history/provenance; and
- automation or public-contract behavior that is intentionally not implemented.

The goal is current threat/control discoverability, not reproducing STRIDE boilerplate for risks that do not exist in the domain.

## 9. Focused security review threshold

A separate `<capability>-security-review.md` is required when a capability has one or more independently material security characteristics that would make the domain profile too shallow or ambiguous, especially when it:

1. owns secrets, credentials, recovery material, signing keys, or bearer tokens;
2. is anonymously reachable or crosses an untrusted/external network boundary;
3. performs destructive, retention, anonymization, legal-hold, or cross-tenant operations;
4. establishes the application's tenant boundary;
5. handles private files/media or other storage with an independent trust boundary;
6. is shared infrastructure whose replay/payload behavior can affect multiple domains; or
7. has a distinct abuse/replay/concurrency model with security consequences.

A model/controller count alone does not justify a focused review.

## 10. Focused review format

A living focused review uses this metadata:

```text
**Document type:** Living capability security review
**Status:** Current
**Owning domain:** <Domain>
**Capability:** <Capability>
**Code owner:** `app/Domain/<Domain>`
```

Required sections, in order:

1. `## 1. Scope and security objective`
2. `## 2. Assets and sensitive data`
3. `## 3. Trust boundaries`
4. `## 4. Threats and controls`
5. `## 5. Authorization, tenancy and privacy`
6. `## 6. Integrity, replay and concurrency`
7. `## 7. Secret and data lifecycle`
8. `## 8. Abuse limits and failure behavior`
9. `## 9. Verification and evidence`
10. `## 10. Residual risks and external controls`

Focused reviews document current controls. Historical increment security reviews may retain their accepted format and are indexed as historical evidence rather than silently rewritten into this living format.

## 11. Secrets and credentials

Documentation must never contain live secret material, token examples that could be mistaken for credentials, recovery codes, private keys, production payloads, or exploit instructions that materially increase operational risk.

Security docs state:

- where secret state is generated;
- whether plaintext is ever displayed and for how long;
- how persistence protects/verifies it;
- how rotation/revocation/expiry works;
- what must never enter logs/audit/outbox/exports; and
- which production secret-store/egress controls remain external evidence.

## 12. Privacy, retention, deletion and anonymization

Where a domain stores personal/private data, the profile states:

- purpose and audience;
- retention owner/state;
- correction/history behavior;
- deletion/anonymization behavior;
- legal-hold or evidence-preservation constraints when applicable;
- export/disclosure boundary; and
- what history is intentionally retained after identifying detail is removed.

Platform may orchestrate lifecycle but does not silently acquire semantic ownership of another domain's data.

## 13. Audit, logs and evidence

Security documentation identifies what privileged/security-relevant transitions require attributable evidence and what sensitive fields must never be copied into generic audit/log/outbox payloads.

Repository validation may prove application controls, tests, static analysis, dependency/code/container scanning, immutable-image behavior, staging, and recovery tooling. It does not prove real production ingress, DNS, egress, firewall, managed-secret, malware-scanner, alert-routing, support-coverage, or operator-identity configuration unless external evidence is explicitly recorded.

## 14. Shared baseline reconciliation

A domain profile may reference the shared baseline for a cross-domain rule but must still state how the rule applies locally. If a domain needs behavior that conflicts with the baseline, the baseline and domain documentation must be reconciled in the same change or the P2 gate fails.

## 15. Historical security evidence

`docs/security/phase-*-threat-model.md` and accepted increment security reviews remain historical evidence. They may be referenced for verification/history, but living profiles must use present-tense current behavior and explicitly call out superseded/deferred historical statements when relevant.

Historical evidence is not edited merely to match current naming unless a broken link/ownership claim would otherwise mislead readers.

## 16. P2 coverage inventory

`security-coverage-matrix.md` is the authoritative P2 inventory. It must enumerate:

- all 14 canonical domains;
- each domain security profile;
- every required focused living security review;
- historical evidence used as a source;
- shared baseline reconciliation state; and
- completion status.

P2 is coverage-complete only when every required row is `Complete`.

## 17. CI enforcement

P2 CI should deterministically enforce high-signal rules:

- all 14 domain security profiles exist;
- profile metadata and numbered headings follow this standard;
- the frozen focused-review inventory exists;
- living focused reviews use required metadata/headings;
- each profile links its domain README and shared security baseline;
- each profile indexes all living focused reviews in its directory;
- domain-specific living security docs do not appear at top-level `docs/security/`; and
- normal repository link/naming/ownership gates remain green.

CI should not force cosmetic rewrites of accepted historical security evidence.

## 18. P2 exit gate

`DCP-P2` is complete only when:

1. 14/14 domain security profiles are complete;
2. 100% of the frozen focused living review inventory is complete;
3. the shared security baseline agrees with every living profile/review;
4. privacy/retention/secret/destructive-operation coverage is explicit wherever applicable;
5. no domain-specific living security evidence is misplaced at the shared root;
6. all required navigation/index links resolve;
7. P2 security-documentation CI passes; and
8. protected repository checks pass on the exact candidate and final evidence/status heads required by the Documentation Completion Program.

Later DCP phases may deepen operations, interfaces, and test traceability, but they cannot be used to excuse a missing P2 security/privacy/data-protection contract.