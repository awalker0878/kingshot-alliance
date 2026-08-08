# Platform scale and administration

## Purpose

Phase 6 separates product operations from alliance administration and adds the controls needed to operate many alliances safely. Platform administration is a cross-tenant capability; it is not an alliance role and does not reuse alliance permission rows.

## Administrator boundary

An active row in `platform_administrators` is necessary but insufficient for web access. The account must also have a verified email, confirmed MFA, an authenticated session, and recent password confirmation. Horizon uses the same verified/MFA/platform-admin boundary. Platform routes do not activate `AllianceContext`.

Bootstrap is explicit: `php artisan platform:admin:grant user@example.com` grants a platform administrator. It never disables the MFA requirement. Administrator grant/revocation changes are audited. Self-revocation is rejected so operators cannot accidentally remove the final access path from their current session.

Support impersonation is intentionally absent. The implementation plan allows it only if approved; Phase 6 records no such approval.

## Alliance lifecycle

The platform console can provision, suspend, close, logically delete, restore, export, and transfer ownership.

Lifecycle states are `active`, `suspended`, `closed`, and `deleted`. Alliance tenant context and alliance API authentication accept only `active`. Closing records a restoration/retention deadline. Logical deletion requires the alliance to be closed first and is blocked by an active alliance legal hold. Restoration of a deleted alliance is allowed only before the retention deadline.

Lifecycle mutations require a reason, execute under a transaction/row lock, emit audit events, and publish outbox events.

## Ownership transfer

Ownership transfer requires an active target membership in the same alliance. The target receives the owner role; previous owners are demoted to leader. The operation is audited and emits an outbox event.

## Plans and entitlements

Phase 6 introduces a payment-independent plan foundation:

- `platform_plans`
- `platform_plan_entitlements`
- `alliance_plan_assignments`

The standard plan currently defines limits for active/pending members, storage bytes, active API credentials, and active webhook subscriptions. New and pre-existing alliances receive the standard plan. Invitation issuance and media upload enforce the corresponding limits in their owning domains. Integrations enforce credential/webhook limits before creation.

This is deliberately not payment processing. Future commercial billing can map purchased products to plan assignments without changing domain quota enforcement.

## Alliance configuration and feature flags

`alliance_platform_settings` stores retention window, queue partition, API availability, and webhook availability. `alliance_feature_flags` stores alliance-local flags and optional JSON configuration. Platform changes are audited. Feature flags do not create hidden compatibility behavior; they are explicit product controls.

## Usage and operational visibility

Usage snapshots capture active members, media storage, API credentials, webhook subscriptions, and unpublished outbox messages. The platform console also exposes queue sizes, failed-job count, pending/failed webhooks, and fleet lifecycle counts. Horizon remains the detailed worker/queue operational surface.

Queues are partitioned into core/default, notifications, integrations, and maintenance classes so webhook retries cannot consume all application workers.

## Data lifecycle

Legal holds can target a user or alliance. Active holds block destructive processing for that subject.

Account deletion has a seven-day cooling-off period. It is blocked while the user is a platform administrator, owns an active alliance, or is under legal hold. Eligible requests revoke tokens, end active memberships, and anonymize the user while preserving pseudonymized audit/business history.

Operational retention redacts old webhook payload/error bodies after 30 days, removes long-revoked API credentials after 90 days, and removes old usage/export metadata after one year.

## Alliance export

Platform administrators can generate a tenant-complete JSON export by discovering PostgreSQL tables that carry `alliance_id` and exporting rows scoped to the requested alliance. Known secret/verifier columns are redacted. Every export records schema version, requester, row count, SHA-256 checksum, generated time, and per-table counts in the audit event. A 100 MiB synchronous safety bound prevents an operator request from monopolizing the web worker.

## Boundaries

Platform owns cross-tenant administration, plans, settings, legal holds, retention orchestration, and usage. Integrations owns API credentials/webhooks. Alliance and feature domains remain responsible for their own business records and authorization. `Kingdoms` remains documentation-only.
