# DCP-P2 security, privacy, and data-protection completeness exit report

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase exit report  
**Phase:** `DCP-P2` — Security, privacy, and data-protection completeness  
**Status:** Complete — final evidence/status head pending protected validation  
**Content candidate SHA:** `645c943e59439840d3563452d97612eb17d63b10`  
**Validated evidence SHA:** `eea41be6bf45820a7f3ab06f57cc24703e7d2b8e`

## 1. Outcome

The DCP-P2 security/privacy/data-protection inventory is complete and passed protected validation on the corrected evidence head.

The program ledger may advance to `DCP-P3` in the final evidence/status commit chain, but that resulting final head must itself pass protected checks before P2 closure is authoritative under the Documentation Completion Program hard gate.

## 2. Standard adopted

DCP-P2 introduced [Security documentation standard](security-documentation-standard.md), which defines:

- source-of-truth precedence between executable behavior, shared baseline, living domain profiles, focused reviews, and historical evidence;
- mandatory domain-owned `security/README.md` profiles for every canonical code domain;
- a deterministic 12-section living domain security profile;
- a risk-based threshold for focused living capability security reviews;
- a deterministic 10-section focused-review format;
- required treatment of assets, trust boundaries, authorization/tenancy/privacy, integrity/replay/concurrency, secrets, destructive operations, audit/evidence, residual risk, and production-only controls; and
- high-signal CI enforcement without rewriting accepted historical security evidence for cosmetics.

## 3. Frozen inventory result

The completed [Security coverage matrix](security-coverage-matrix.md) covers all 14 canonical domains.

Coverage accepted:

- **14/14** living domain security profiles;
- **9/9** new focused living capability security reviews required by the frozen P2 inventory; and
- the complete existing Kingdoms K1–K3 domain security-review set retained and indexed by the normalized living Kingdoms security profile.

## 4. New focused living security reviews

P2 added focused reviews for independently high-risk boundaries:

- Alliances — active tenant context/snapshot propagation;
- Content — private/untrusted media upload, screening, storage, and public branding eligibility;
- Identity — TOTP MFA and one-time recovery-code lifecycle;
- Integrations — Alliance-bound read-only API credentials/scopes;
- Integrations — signed outbound webhooks, eligibility, endpoint/egress and retry behavior;
- Memberships — expiring email-bound bearer invitations;
- Platform — cross-tenant lifecycle/legal-hold/deletion/restoration/anonymization/export;
- Platform — shared transactional outbox payload/replay/tenant boundary; and
- Recruitment — public/invitation-only applicant intake and personal-data boundary.

Audit, Authorization, Contributions, Events, Notifications, and Rallies were explicitly reviewed and remain profile-only because the current implemented boundary is coherent without a separate focused living review.

## 5. Kingdoms security normalization

`docs/domains/kingdoms/security/README.md` is now a current P2 living domain security profile. It preserves navigation to all accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` security review records rather than rewriting their accepted historical formats.

The living profile makes current privacy/authorization boundaries explicit for neutral game identity, Alliance-owned roster/snapshot/import/transfer state, private diplomacy/contact data, descriptive intelligence, append-oriented history, and explicit machine/automation non-capabilities.

## 6. Shared security baseline reconciliation

The repository-wide [Security baseline](../security/security-baseline.md) remains the current cross-domain security policy.

P2 profiles do not duplicate or weaken that baseline. They explain how shared rules apply to each owner and distinguish application/repository controls from production evidence that the repository cannot prove, including real ingress/egress/firewall/DNS, managed secret ownership, malware-scanner deployment, operator identity, external recipient handling, and similar infrastructure/process controls.

Historical Phase 1–6 threat models remain historical evidence for when controls were introduced; they are no longer the only route to discover current domain security behavior.

## 7. Privacy and data-protection completeness

P2 explicitly documents, where applicable:

- public versus member/tenant/private/manager/cross-tenant data classes;
- applicant/account/member/private operational data boundaries;
- bearer/API/webhook/MFA secret handling and non-logging rules;
- data-minimized audit/outbox/log/export payload behavior;
- legal holds and destructive-operation fail-closed rules;
- account/candidate/tenant anonymization, retention, restoration and evidence preservation;
- externally transmitted payload minimization and recipient/egress residual risk; and
- feature-domain semantic ownership during Platform-orchestrated lifecycle operations.

## 8. Trust, abuse and integrity completeness

The living profiles/reviews cover the applicable high-impact abuse classes, including:

- cross-Alliance IDOR and stale tenant context;
- role/permission escalation and last-Owner safety;
- authentication/recovery downgrade and replay;
- invitation/API credential theft/replay;
- malicious media upload and cross-tenant storage access;
- webhook event overexposure, SSRF/egress risk, duplicate delivery and retry amplification;
- registration/report/outbox concurrency and at-least-once behavior;
- applicant PII overexposure and public/private pipeline separation; and
- destructive lifecycle/export misuse and legal-hold bypass.

Explicit non-capabilities prevent game/reference identity, workflow responsibility, internal outbox events, or generic integrations from silently becoming authorization or public contracts.

## 9. Security navigation and ownership

Security navigation exposes:

- the shared baseline and DCP-P2 standard/inventory from `docs/security/README.md`;
- all 14 living security profiles from the shared security and domain indexes; and
- focused living reviews from their owning domain profile.

Domain-specific current security behavior remains under `docs/domains/<domain>/security/`. Top-level `docs/security/` remains cross-domain policy, historical phase-wide threat evidence, and production security/go-live material.

## 10. CI enforcement

`tests/Architecture/RepositoryStructureTest.php` verifies:

- every one of the 14 code domains has a matching living `docs/domains/<domain>/security/README.md`;
- profile metadata and the required 12 security-profile sections appear in order;
- every profile links the owning domain and shared security baseline;
- the exact frozen nine-review P2 focused living inventory exists;
- every required focused review is indexed by its domain security profile;
- focused-review metadata and the required 10 sections appear in order; and
- those living focused reviews are not misplaced under top-level `docs/security/`.

Existing filename, documentation ownership, local Markdown link, P1 contract/capability, and Kingdoms evidence-placement checks remain active.

## 11. Validation history and accepted evidence

The first P2 evidence head, `50beb0f49b77b5321722cfa337b6334f47a8e126`, passed Dependency Review and CodeQL but CI run `31503644300` failed only on local Markdown-link integrity. The nine new focused reviews referenced the shared security directory one level too high (`../../../../security/...` instead of `../../../security/...`). Pint, PHPStan, frontend, and migrations were green on that run.

All affected baseline/threat-model links were corrected without changing security semantics. The corrected content candidate became `645c943e59439840d3563452d97612eb17d63b10`, and the corrected evidence head `eea41be6bf45820a7f3ab06f57cc24703e7d2b8e` passed all protected checks:

- Dependency Review `31504587302` — success;
- CodeQL `31504587346` — success;
- CI `31504587198` — success;
  - frontend quality/build — success;
  - PostgreSQL migrations — success;
  - Pint — 483 files;
  - PHPStan/Larastan — 345/345, 0 errors;
  - ParaTest/PHPUnit — 369 tests / 6,908 assertions;
  - immutable production-image build — success;
  - ephemeral staging deployment — success;
  - backup/restore demonstration — success; and
  - image vulnerability scan — success.

## 12. Deferred work is phase-owned, not a P2 gap

P2 documents security/privacy/data-protection behavior at current contract depth. Deeper concerns remain intentionally sequenced into:

- `DCP-P3` — operational diagnostics, reliability, recovery, capacity, replay and operator procedures;
- `DCP-P4` — complete interface/event/job/API/webhook/import/export contract inventory;
- `DCP-P5` — security/privacy assertion-to-test/evidence traceability;
- `DCP-P6` — architecture/governance consolidation; and
- `DCP-P7` — maintenance automation/final acceptance.

Those phases may deepen evidence but cannot be used to reopen or excuse a missing P2 tenant/privacy/secret/trust/destructive-operation/security non-capability.

## 13. Final hard gate

The P2 content and corrected evidence candidate are accepted. This report and the program status ledger now form the final evidence/status chain. That final branch head must pass protected Dependency Review, CodeQL, and the complete CI workflow before `DCP-P2` is treated as authoritatively closed and `DCP-P3` becomes the operative phase for the next `continue`.
