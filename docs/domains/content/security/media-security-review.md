# Content media security review

[← Content security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Content  
**Capability:** Private Content media  
**Code owner:** `app/Domain/Content`

## 1. Scope and security objective

Protect untrusted uploaded bytes from becoming cross-tenant, executable, unscreened, or unintentionally public content. A stored object is not publicly usable until Content accepts its lifecycle/security state.

## 2. Assets and sensitive data

Assets include tenant-owned media metadata, private object paths, checksums, MIME/size metadata, scanner state/result, lifecycle state, and public branding attachment references.

Media bytes are private tenant data until a supported same-Alliance clean/active image is intentionally selected for public presentation.

## 3. Trust boundaries

- Privileged manager upload → request/application validation.
- Untrusted bytes → `MediaScanner` contract.
- Accepted bytes → private tenant-prefixed object storage.
- Media record → logo/banner attachment eligibility.
- Public branding request → controlled same-Alliance clean active image representation.

Production malware scanner and object-storage policy are external runtime boundaries.

## 4. Threats and controls

| Threat | Security impact | Current controls |
| --- | --- | --- |
| Oversize/disallowed file | Resource abuse or unsafe content | HTTP/application MIME and configured size bounds. |
| Executable/script/polyglot upload | Client/server compromise | Replaceable scanner contract plus baseline signature screening; scanner failure fails closed. |
| Cross-tenant media ID/path | Data disclosure | Tenant-prefixed private storage and same-Alliance query/re-resolution. |
| Direct row insertion bypasses screening | Unsafe public object | Lifecycle invariant: only successfully screened clean/active media is usable. |
| Rejected/unscannable media becomes public | Malware disclosure | Rejected/failure state never degrades to acceptance. |
| Archived/invalid asset remains branding | Broken/security-inconsistent public state | Attached logo/banner must remain clean/active; detach/replace before archive. |
| Public filesystem/object ACL accidentally exposes all media | Bulk disclosure | Accepted Content storage is private; public presentation is application-mediated. |

## 5. Authorization, tenancy and privacy

Management requires active Alliance context, `content.manage`, verified identity, and applicable recent password confirmation. Media IDs are re-resolved under the active Alliance.

Public branding access exposes only the selected safe representation, not general tenant object paths or media listings.

## 6. Integrity, replay and concurrency

Upload acceptance is stateful: repeated requests cannot attach another tenant's object or bypass scanner state. Branding/archive transitions preserve the invariant that a currently attached public branding asset is valid and active.

Storage/scanner failure leaves the asset unusable rather than partially accepted. Operators repair dependencies instead of forcing state directly.

## 7. Secret and data lifecycle

Content stores media bytes/metadata, not scanner credentials. Scanner/object-store credentials belong in runtime secret management and must never be persisted in media records, logs, audit/outbox payloads, checksums, or docs.

Rejected files are not retained as normal usable media. Archival removes assets from supported selection while preserving lifecycle state according to the domain contract.

## 8. Abuse limits and failure behavior

Configured upload size/type limits bound resource use. Scanner or storage failure fails closed. Cross-tenant IDs and invalid branding references fail validation. There is no public arbitrary upload/hosting endpoint.

The current default documented maximum is 8192 KiB unless runtime configuration changes the bound.

## 9. Verification and evidence

Tests cover MIME/size bounds, tenant-specific ownership, scanner acceptance/rejection, cross-tenant rejection, private storage, branding eligibility, and attached-asset archival safety.

Historical source: [Phase 2 threat model](../../../security/phase-2-threat-model.md). Shared policy: [Security baseline](../../../security/security-baseline.md).

## 10. Residual risks and external controls

The built-in scanner hook is defense in depth, not proof of enterprise malware detection. Production must bind the approved scanner service and maintain private object-storage/egress/serving controls. Repository tests cannot prove external scanner signatures, bucket policy, CDN configuration, or future object-store administration.