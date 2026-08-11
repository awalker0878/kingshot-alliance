# Content security profile

[← Content domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Content  
**Code owner:** `app/Domain/Content`  
**Primary security boundary:** explicit public/member visibility plus tenant-scoped privileged authoring and private-media controls

## 1. Security purpose and scope

Content protects authored Alliance material from unintended disclosure or execution while supporting intentionally public presentation. It covers publication/visibility, revisions, categories/search, authoring privilege, and private media.

The independent untrusted-upload/storage/presentation boundary is reviewed in [Media security review](media-security-review.md).

## 2. Assets and sensitive data

Assets include draft/scheduled/member-only/archived content, immutable revision history, author attribution, Alliance presentation settings, category/search metadata, and private media objects/checksums/lifecycle state.

Published public content is intentionally public. Draft/member-only/revision-history data and private media remain tenant private unless a supported transition explicitly makes a safe representation public.

## 3. Actors, authentication and authorization

Anonymous users may read only active-Alliance public profile fields and published public Content. Authenticated active members may additionally read permitted member-visible Content.

Management requires active Alliance context, `content.manage`, verified identity, and recent password confirmation where required. UI visibility is not a substitute for server-side authorization.

## 4. Tenant and privacy boundaries

Content/category/revision/media identifiers are re-resolved under the active Alliance for privileged/member flows. Public queries start from a public-visibility base query rather than filtering an unrestricted dataset afterward.

Recruitment application availability may be presented publicly but remains Recruitment-owned; private candidate/member data must not be copied into Content merely because Content has a public surface.

## 5. Trust boundaries and data flows

Material boundaries are anonymous browser → public Content query, authenticated member → member Content query, privileged manager → authoring/publication workflow, scheduler → due publication transition, and untrusted upload → scanner/private storage → controlled presentation.

The media boundary is detailed in the focused review.

## 6. Threats, abuse cases and controls

Threats include cross-Alliance IDOR, draft/member-only disclosure, stored XSS/unsafe HTML, revision restore silently republishing old data, malicious file upload, cross-tenant media reference, scheduled-publication races, and unbounded/search-based hidden-content enumeration.

Controls include tenant-scoped re-resolution, explicit publication/visibility predicates, escaped/plain-text rendering rather than trusted arbitrary HTML, restore-to-draft semantics, bounded public/member queries, row locks for due publication, and private-media screening/storage controls.

## 7. Integrity, concurrency and idempotency

Editing or restoring live/history content creates a new draft state; publication is always a separate privileged transition. Scheduled publication claims/rechecks due rows transactionally so multiple schedulers cannot independently publish the same state transition.

Historical revisions are immutable and are not destructively rewritten to represent current state.

## 8. Secrets and credential handling

Content owns no authentication or machine credentials. Uploaded files and authored text must never be treated as places to persist secret values. Logs/audit/outbox evidence uses identifiers/status/checksum metadata as needed and avoids raw private content or media bytes.

Production malware-scanner credentials/configuration belong in managed runtime configuration, not Content documentation or persistence.

## 9. Destructive operations, retention and deletion

Archival and revision history preserve explainability rather than destructively deleting prior authored state through normal editing. Media archival/deletion follows the media lifecycle and tenant/object-ownership rules.

Platform lifecycle may orchestrate broader tenant/account retention/deletion but does not acquire Content semantic ownership.

## 10. Auditability, observability and evidence

Material authoring/publication/media transitions are attributable through Audit/outbox where required. Operators distinguish publication state, visibility, revision state, media scanner/storage state, and tenant authorization.

Tests protect public/member/draft isolation, tenant identifier rejection, revision restore, scheduled publication, safe rendering, and private media controls. See the shared [Security baseline](../../../security/security-baseline.md).

## 11. Residual risks and explicit non-capabilities

Repository validation cannot prove the real production malware-scanning service, object-storage policy, CDN/ingress behavior, or browser/device environment. Those remain deployment evidence when applicable.

Content does not accept trusted arbitrary HTML, expose unpublished/member-only data publicly, bypass media screening, or own Recruitment/member private workflow data.

## 12. Focused reviews and related documentation

- [Media security review](media-security-review.md)
- [Content media contract](../media.md)
- [Recruitment security profile](../../recruitment/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 2 threat model](../../../security/phase-2-threat-model.md)
