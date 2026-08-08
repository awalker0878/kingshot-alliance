# Security baseline

[← Security documentation](README.md)

This document describes the **current** repository security baseline after Phases 0–6 and repository-controlled production hardening. Phase-specific threat models remain historical evidence for when controls were introduced; this file is the consolidated present-tense baseline.

The authoritative real-production go/no-go decision is maintained in [`../product/production-launch-approval.md`](../product/production-launch-approval.md). Passing repository checks does not prove infrastructure controls that must be evidenced in the production environment.

## Application and transport

- Externally reachable hosted environments use HTTPS. Production startup fails closed when `APP_URL` is not HTTPS, debugging is enabled, secure session cookies are disabled, or required hosted database protections are missing.
- The ephemeral CI staging demonstration may use loopback HTTP only when the loopback exception is explicitly enabled.
- Hosted runtime requires a valid application key, non-placeholder application version, immutable release SHA, PostgreSQL, and Redis-backed cache/queues/sessions.
- Session payloads are encrypted; browser sessions use HTTP-only, SameSite protection and secure cookies in production.
- State-changing browser requests use CSRF protection.
- Responses include clickjacking, content-sniffing, referrer, permissions, opener, and production HSTS controls. Security headers also apply to rendered error responses.
- Trusted proxies are configured explicitly. Trust-all proxy mode requires a separate explicit opt-in and is acceptable only behind a controlled ingress boundary.
- Dynamic web requests route through the Laravel front controller; arbitrary PHP execution paths are rejected.
- Public health responses are stateless, non-cacheable, and expose aggregate status/correlation only rather than dependency details or release metadata.
- Authentication, API, recruitment-intake, and other abuse-sensitive routes use named rate limits.

## Identity and authentication

- User identity is global; a user may belong to multiple alliances without duplicating identity rows per tenant.
- Email addresses are normalized before uniqueness-sensitive authentication/registration operations.
- Privileged alliance operations require verified identity and recent password confirmation where defined by the owning route/policy boundary.
- Password reset does not disclose whether an account exists. Password reset/change invalidates affected long-lived credentials and stale authenticated sessions as implemented by the identity domain.
- MFA uses TOTP. Secrets use encrypted model casts and are excluded from serialization.
- MFA recovery codes are stored as hashes, displayed only when created/regenerated, and consumed once.
- MFA challenge attempts are separately rate limited and successful challenges regenerate the authenticated session boundary.
- Security-relevant identity transitions are attributable through the audit domain.

## Platform administration

- Platform administration is a separate cross-tenant grant; it is not an alliance role and alliance ownership does not confer platform access.
- Platform web administration requires an active platform-administrator grant, verified email, confirmed MFA, and recent password confirmation on privileged platform routes.
- Production launch readiness requires at least two active operational platform administrators and rejects active grants whose user lacks verified email or confirmed MFA.
- A platform administrator cannot revoke their own grant through the managed revocation path.
- Horizon worker visibility is restricted by `Horizon::auth` to active platform administrators with verified email and confirmed MFA.
- Pulse dashboard routes are not exposed; application code explicitly ignores Pulse routes. Any production telemetry export/recording must retain the same privacy, authorization, and cardinality constraints as the structured observability model.
- Support impersonation is not approved and is not implemented.

## Alliance tenancy and authorization

- Active alliance context is explicit and is revalidated against an active membership before tenant-scoped access.
- Tenant-owned queries receive an explicit alliance identifier rather than depending on hidden global tenant state.
- Tenant context propagates through an immutable snapshot used for requests and for tenant-prefixed asynchronous/cache/storage/export/log boundaries where applicable.
- Tenant storage/export helpers reject unsafe path segments.
- Alliance-scoped authorization checks both membership and the applicable tenant-scoped permission/policy.
- Composite tenant foreign keys protect critical same-alliance relationships at the database layer, including role assignment.
- Membership administration enforces hierarchy and last-owner safety; deactivation/removal does not preserve hidden role privilege for later reactivation.
- Cross-domain code must use intentional public contracts rather than reaching into another domain's persistence internals, reducing accidental authorization and isolation bypasses.

## Invitations and membership lifecycle

- Invitation bearer tokens are high entropy, stored as hashes, bound to the intended email, expire, are one-time use, and rotate on resend/replacement.
- Replacement/acceptance/revocation behavior uses transactions/locking where required to avoid concurrent duplicate lifecycle transitions.
- Membership and invitation changes produce attributable audit/outbox evidence for meaningful state transitions.

## Content and media

- Anonymous access is limited to public content/profile fields for active alliances; members-only content requires an authenticated active-alliance context.
- Content-management mutations require the owning permission/policy and the privileged confirmation boundary defined by the content routes.
- Historical revisions are immutable evidence; restoring/editing a historical version returns content to a controlled draft path rather than silently republishing it.
- Authored text is rendered safely; raw HTML execution is not a supported content feature.
- Private media uses tenant-scoped storage, bounded size/type validation, checksum/lifecycle tracking, and the configured media-scanner hook.
- Hosted durable private media is treated separately from PostgreSQL backup. A database backup must never be represented as proof that media binaries were recovered.

## Events, reminders, and rallies

- Event and rally management remains alliance-scoped and policy protected; privileged coordinator mutations use the common confirmation boundary.
- Event recurrence persists canonical time data and is tested across time-zone/DST behavior.
- Registration capacity/waitlist transitions are serialized where necessary to avoid over-allocation and duplicate promotion.
- Reminder delivery uses persisted delivery/outbox state and idempotent/retry-safe processing to prevent duplicate notification behavior.
- Rally guidance is effective-dated/configurable data with source/rationale rather than hidden hard-coded game advice.

## Recruitment

- Recruitment mode has one authoritative source in the recruitment domain; public content composes that state rather than maintaining a duplicate writable status.
- Application intake is rate limited and bounded by the configured recruitment mode.
- Candidate, notes, reviewer, tag, decision, and conversion workflows are alliance scoped.
- Accepted-candidate conversion uses the membership invitation contract rather than direct access to membership persistence internals.
- Unsuccessful-candidate retention/anonymization is enforced through the documented retention process.

## Contributions, reporting, and exports

- Contribution reporting distinguishes recorded facts, calculated metrics, and subjective assessments.
- Calculation versions/effective periods are preserved so historical totals remain explainable after rule changes.
- Corrections/reversals preserve history rather than mutating past records into an untraceable final value.
- Member self-service and leader management views remain tenant/policy scoped.
- Exports are authorized, tenant specific, attributable, and bounded; report metadata/version/checksum are retained where implemented.
- Comparative/leaderboard views remain controlled features rather than an assumption that all alliances must expose competitive ranking.

## API credentials and webhooks

- Phase 6 API credentials are scoped/revocable and do not silently broaden into unrestricted platform access.
- Webhook subscriptions are tenant scoped, signed, rate/bounds controlled, retryable, and retain delivery diagnostics without exposing secrets.
- Integration work is isolated onto the integrations queue partition so retry storms cannot consume all core queue capacity.
- Endpoint/application validation reduces SSRF exposure but does **not** prove protection against DNS rebinding or infrastructure routing changes. Production egress policy must block metadata, private, and management networks and must be evidenced outside the repository.

## Transactional outbox and queues

- Meaningful persisted domain changes write outbox state in the same database transaction where required.
- Publication is at-least-once; consumers rely on stable idempotency/deduplication semantics.
- PostgreSQL publishers claim eligible rows with locking/lease behavior, bounded retry/backoff, attempts, and bounded error diagnostics.
- Scheduled publishers/maintenance jobs use overlap and single-server controls where duplicate concurrent execution would be unsafe.
- Hosted Horizon separates core (`default`, `notifications`), integrations, and maintenance queue capacity.

## Secrets and sensitive configuration

- Secrets are injected at runtime and are never committed to source control.
- Production secret values belong in an approved managed secret store; documentation records requirements/ownership without secret material.
- Deployment environment files are owner-readable only and are excluded from commits and image build contexts.
- Logs, exception context, CI output, support tooling, and exports must not expose passwords, tokens, signing secrets, MFA material, application keys, or private credentials.
- Repository/build exclusions protect deployment environments, backup output, runtime keys, credentials, and private application storage from accidental inclusion.

## Data protection, retention, and deletion

- Production PostgreSQL transport is encrypted and plaintext fallback is rejected by hosted configuration validation.
- Object/private storage defaults to private visibility and fails loudly on write errors.
- Data purpose, retention, correction, export, and deletion behavior is defined by the owning domain.
- Account deletion anonymizes retained business/audit history where referential integrity or legal/operational evidence requires preservation.
- Legal holds block deletion workflows that would destroy held records.
- Alliance deletion is logical/recoverable through its retention window rather than immediate destructive removal.

## Backup, restore, and recovery

- A populated PostgreSQL schema is backed up before migrations unless an explicitly approved release procedure says otherwise.
- Backup archives are checksummed and validated before restore; incomplete output is not treated as a completed backup.
- Backup provenance identifies the source release rather than substituting the incoming deployment target.
- Restore validates archive/manifest integrity and database readiness before destructive service transitions.
- Generated backup material is excluded from source control and production image build contexts.
- CI demonstrates database backup/restore tooling, but real production approval additionally requires evidence for **database + private media + application-key recovery together**.

## Supply chain and release integrity

- Composer/npm audits, Dependency Review, CodeQL, and production-image vulnerability scanning run in protected validation.
- External GitHub Actions are pinned to reviewed immutable commit SHAs rather than mutable tags/branches.
- Release images are immutable and carry source revision/version/license metadata; deployment verifies expected image identity and release metadata.
- Runtime images use targeted copies and exclude development/test/deployment material that is not required at runtime.
- Deployment is digest oriented; mutable image tags are not sufficient production identity.

## Observability and incident response

- Request IDs and W3C trace identifiers correlate web/error/async diagnostics without exposing secrets.
- Structured logs include the appropriate domain action, tenant/actor context and outcome while avoiding sensitive values.
- Metrics use bounded labels (for example named routes rather than raw uncontrolled paths).
- Readiness/liveness are separate; operational alerts must map to actionable runbooks and accountable owners.
- Incident response follows [`../operations/runbooks/incident-response.md`](../operations/runbooks/incident-response.md).

## Production evidence boundary

Repository controls can establish code, migration, test, static-analysis, dependency/code/container scanning, immutable-image, staging, and recovery-tooling evidence. They cannot establish that a real deployment has correct HTTPS/ingress, trusted proxies, webhook egress, capacity, alert routing, DNS/mail/object-storage/secret ownership, platform-admin identities, support coverage, or complete production recovery.

Those items remain **Pending** until the accountable deployment owner records non-secret evidence in the production approval process. See [`production-launch-security-review.md`](production-launch-security-review.md) and [`../product/production-launch-approval.md`](../product/production-launch-approval.md).
