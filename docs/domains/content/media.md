# Content media

[← Content domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Content

## 1. Purpose

Defines the private Alliance-scoped media lifecycle used for Content branding and authored-content assets.

Media is a storage/security capability separate from publication state: an uploaded file does not become publicly usable merely because bytes exist in storage.

## 2. Scope and non-scope

In scope:

- tenant-owned media records and private storage paths;
- MIME/size validation;
- security-screening state;
- usable/active versus archived media lifecycle;
- logo/banner attachment safety; and
- storage/plan constraints supplied by Platform.

Out of scope:

- authored Content revision/publication lifecycle;
- arbitrary public file hosting;
- bypassing scanning by directly creating media rows; and
- external object-storage administration.

## 3. Model and state

A media asset belongs to one Alliance and references private tenant-specific stored bytes plus metadata required to validate/present it safely.

The accepted lifecycle distinguishes at minimum:

- uploaded/under validation or screening;
- clean/active and therefore eligible for supported use;
- rejected/unusable; and
- archived.

Only clean active image assets may be selected for the current public logo or banner.

## 4. Invariants

1. Media is always Alliance scoped.
2. Storage paths are tenant specific/private.
3. MIME type and configured size bounds are enforced before acceptance.
4. Security-screening failure never degrades into automatic acceptance.
5. Rejected files are not retained as usable media.
6. Public branding may reference only clean active image media from the same Alliance.
7. A media asset currently attached as logo/banner cannot be archived until detached.
8. Media records do not grant permission to view unrelated tenant storage.

## 5. Workflows

### Upload

A manager with Content authority submits a file. The application validates configured type/size constraints and passes the file through the accepted screening/storage flow.

The documented default maximum is 8192 KiB unless runtime configuration changes that bound.

### Make usable

Only a successfully screened asset becomes eligible for supported presentation/attachment.

### Select branding media

A clean active same-Alliance image can be selected for logo/banner presentation.

### Archive

Archival removes the asset from normal usable selection. Current logo/banner assets must first be replaced or detached.

### Rejection/failure

Rejected or persistently unscannable files are not made usable. Operators repair scanner/storage dependencies rather than bypassing state in the database.

## 6. Authorization, tenancy and privacy

Media management requires authenticated, verified active-Alliance context and `content.manage`; privileged mutations use recent password confirmation where required by the HTTP boundary.

All media IDs are re-resolved under the active Alliance. Private storage must not expose another tenant's path or object.

## 7. Persistence and query semantics

Content owns media metadata/lifecycle state. Production storage is durable S3-backed private storage as configured by the shared runtime.

Queries used for branding/selection begin from same-Alliance clean active media rather than loading arbitrary media and filtering later.

## 8. Events, integrations and background processing

Media state transitions may create audit/outbox evidence where required. There is no accepted public media write API.

Scanning/storage work may use shared runtime services, but Content remains owner of whether an asset is accepted for Content use.

## 9. Failure, idempotency and concurrency

- Oversize or disallowed MIME uploads fail validation.
- Scanner/storage failure fails closed rather than publishing unverified media.
- Repeated requests must not attach another Alliance's asset.
- Archive/branding updates must preserve the invariant that an attached public branding asset remains valid and active.

## 10. Operations and observability

Operators should distinguish validation rejection, scanner failure, storage failure, lifecycle state, and branding-reference constraints.

Use shared configuration/observability documentation for storage/scanner runtime diagnosis instead of manually forcing asset state.

## 11. Tests and validation

Tests should cover:

- MIME and size bounds;
- tenant-specific ownership;
- screening acceptance/rejection;
- public branding selection eligibility;
- inability to archive an attached logo/banner;
- cross-tenant media ID rejection; and
- private-storage/public-presentation boundaries.

## 12. Related documentation

- [Content domain](README.md)
- [Alliances](../alliances/README.md)
- [Authorization](../authorization/README.md)
- [Platform](../platform/README.md)
- [Runtime configuration](../../operations/configuration-reference.md)
- [Security baseline](../../security/security-baseline.md)
