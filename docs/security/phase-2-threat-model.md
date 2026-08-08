# Phase 2 Threat Model — Content and Public Presence

## Scope

This review covers the Phase 2 public alliance profile, public/member content reads, content authoring, revision recovery, scheduled publication, media upload/branding delivery, search/filtering, audit/outbox integration, and the Phase 1 tenant boundary they depend on.

## Protected assets

- Draft, scheduled, archived, and member-only content.
- Historical revisions and author attribution.
- Alliance branding and uploaded media objects.
- Alliance public-profile settings. After Phase 4 integration, recruitment availability is owned by the Recruitment domain and only composed into the public page.
- Tenant identifiers and storage paths.
- Audit/outbox evidence for content and media mutations.

## Trust boundaries

1. Anonymous visitors may read only active-alliance public-profile fields, clean attached branding images, authoritative Recruitment-domain availability state, and published public content.
2. Authenticated active members may additionally read published member-visible content for their active alliance.
3. Users with `content.manage` may author and administer content only inside their validated active alliance; mutations require recent password confirmation.
4. The scheduler may promote due scheduled content but must retain alliance scope and use row locking.
5. Uploaded bytes cross an untrusted-input boundary before entering private/S3-backed tenant storage.

## Threats and controls

### Cross-alliance IDOR / route binding

**Threat:** A user guesses another alliance's category, content, revision, or media identifier and reads or mutates it.

**Controls:**
- Public reads resolve by active alliance slug and published-public query; draft/member-only direct slugs return 404.
- Member reads use validated `alliance.context` and active membership.
- Management actions re-query identifiers with `alliance_id` and require `content.manage`.
- Composite PostgreSQL foreign keys bind categories, content, revisions, branding media, and media assets to the same alliance.
- Tenant-specific storage paths are generated through `TenantContextSnapshot`.

### Draft or member-only disclosure

**Threat:** Unpublished or member-only material appears in public lists, search, direct URLs, categories, or revisions.

**Controls:**
- `ContentQuery::publicList()` and `publicBySlug()` require `published`, `public`, due `published_at`, and non-archived state.
- Public category discovery is constrained to categories containing currently published public content.
- Revisions have no public route.
- Editing a live item returns it to draft and clears publication timestamps; a manager must explicitly publish or schedule the new revision.

### Stored XSS / unsafe rich content

**Threat:** Authored content executes script in public/member browsers.

**Controls:**
- Phase 2 stores sanitized plain text and structured metadata, not trusted HTML.
- Vue templates render content through escaped interpolation; `v-html` is prohibited by the Phase 2 accessibility/security guard.
- Existing CSP, content-sniffing protection, and browser security headers remain active.

### Malicious file upload / media confusion

**Threat:** An attacker uploads executable/script/polyglot content, bypasses MIME limits, stores it publicly, or serves another tenant's object.

**Controls:**
- HTTP and application-service layers both enforce configured size/MIME allowlists.
- A replaceable `MediaScanner` contract screens uploads before persistence; the baseline scanner rejects executable/script signatures.
- Content media may not use the public filesystem disk.
- Stored filenames are generated server-side under a tenant prefix and are checksummed with SHA-256.
- Only clean, active, image-typed media attached to the requested alliance branding slot can be streamed publicly.
- Cross-alliance branding references are rejected in application code and by composite foreign keys.

**Residual operational requirement:** production should bind `MediaScanner` to the organization's approved malware-scanning service. The built-in scanner is a defense-in-depth baseline and the required integration hook, not a substitute for enterprise malware detection.

### Revision abuse / unintended republish

**Threat:** Restoring historical content silently makes stale or sensitive text public.

**Controls:** revision restore creates a new draft revision and clears live/scheduled publication state. Publication is always a separate privileged transition.

### Scheduled-publication races

**Threat:** multiple schedulers publish the same content concurrently or duplicate transition evidence.

**Controls:** scheduled publication claims due rows transactionally with row locks; status is rechecked before publication. Outbox rows receive unique event idempotency keys for at-least-once downstream handling.

### Search enumeration / resource abuse

**Threat:** public search leaks hidden content or permits unbounded result amplification.

**Controls:** filters are applied only after the appropriate visibility base query; user values remain bound SQL parameters; public/member result counts are capped by configuration.

### Unauthorized authoring

**Threat:** a normal member alters public presence or content.

**Controls:** management reads and all domain actions check `content.manage`. Mutation routes additionally require verified identity, active alliance context, and recent password confirmation. Mutations record actor/alliance/subject audit evidence and transactional outbox events.

## Verification evidence

Phase 2 feature tests cover public direct-slug isolation, member-only visibility, edit-to-draft behavior, revision restore, scheduled publication, cross-alliance category/media rejection, management authorization/password confirmation, tenant-prefixed media storage, malware-screen rejection, public-branding restrictions, and media lifecycle rules.

The integrated Phase 1–4 regression suite additionally verifies that public recruitment state is derived from Phase 4 `RecruitmentSetting` rather than duplicated in the content-profile schema. See the [Phases 1–4 alignment audit](../product/phases-1-4-alignment-audit.md).

CodeQL, dependency audits, PHPStan, frontend checks, PostgreSQL migration tests, immutable staging deployment, destructive recovery, and Trivy remain mandatory on the final acceptance head.

## Residual risk assessment

No unresolved **critical** or **high** application-security finding is accepted for Phase 2. The production malware-scanner binding and real-device assistive-technology/branding contrast checks are deployment-readiness controls and must be completed before production launch.
