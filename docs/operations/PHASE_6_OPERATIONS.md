# Phase 6 operations

## Daily operating model

Platform operators use `/platform` for fleet status, lifecycle controls, usage, plan/settings, feature flags, legal holds, exports, and administrator grants. Detailed queue worker state is available in Horizon to the same verified/MFA/platform-admin population.

The scheduled control loop runs:

- every minute: publish due content, synchronize/queue event reminders, queue contribution reports, publish transactional outbox messages, and recover due webhook deliveries;
- hourly: process eligible account-deletion requests and capture alliance usage snapshots;
- daily: enforce Phase 6 retention, purge expired recruitment records, and prune old queue batch/failed-job records.

All scheduled jobs use bounded batch sizes. Cluster-wide singleton/overlap protection prevents multiple schedulers from producing unbounded duplicate work.

## Queue partitions

Production and staging split queue capacity into:

- core: `default`, `notifications`;
- integrations: `integrations`;
- maintenance: `maintenance`.

Webhook retries are therefore unable to consume all core workers. During an integration retry storm, reduce or pause the integration supervisor before touching core capacity. Check pending/failed webhook counts and delivery error classes before increasing workers.

## Common diagnosis

### Tenant unavailable

Check alliance lifecycle state. Only `active` alliances may establish tenant context or authenticate API credentials. Review `lifecycle_reason`, retention deadline, and corresponding audit event. A suspended/closed/deleted tenant should fail closed; do not bypass middleware by modifying session state.

### Invitations or uploads blocked

Inspect the assigned plan and entitlement usage. Member quota counts active memberships plus live pending invitations. Storage quota uses persisted media `size_bytes`. Resolve the plan/usage condition rather than weakening validation.

### API credential rejected

Check prefix, revocation, expiry, alliance state, required scope, and `api_access_enabled`. Plaintext API secrets are not recoverable; issue a replacement credential rather than editing the verifier.

### Webhook failing

Inspect subscription state, event allow-list, delivery attempts, HTTP response, and bounded error excerpt. Confirm the target remains an approved public HTTPS destination. Do not weaken private-network protections to make an internal webhook work; use an approved public relay or explicit infrastructure egress design.

### Outbox backlog

Check unpublished outbox count and Horizon core workers. The outbox publisher is idempotent and safe to rerun with bounded batches. Webhook fan-out consumes published events separately on the integrations queue.

### Account deletion blocked

Inspect `account_deletion_requests.blocked_reason`. Remove only legitimate blockers: platform-admin access, alliance ownership, or an approved legal hold. Legal holds must be released explicitly and are audited.

## Bootstrap and administrator recovery

Initial platform admin:

`php artisan platform:admin:grant user@example.com`

The user must still verify email and enable MFA. If all web administrators are unavailable, use controlled console access to grant a verified operational account, then require MFA before web use. Do not add an emergency bypass route.

## Lifecycle runbook

1. Suspend for temporary operational/security isolation.
2. Close when the tenant is intentionally winding down; this starts the restoration/retention window.
3. Export before destructive operations when requested or operationally appropriate.
4. Confirm no legal hold blocks deletion.
5. Logically delete only a closed tenant.
6. Restore only within the allowed retention window.

Lifecycle reasons should identify the ticket/incident/change where available without embedding secrets.

## Capacity review

Review fleet counts, queue depths, failed jobs, usage snapshots, storage, integration counts, and PostgreSQL growth. Capacity changes must preserve queue isolation. Large tenants should be evaluated for storage/export size and webhook load before assigning higher entitlements.
