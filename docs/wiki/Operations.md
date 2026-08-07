# Operations

Kingshot Alliance treats operability as part of every phase exit gate. This page is an index to the main runtime, health, queue, backup, deployment, and incident-response practices.

## Health and correlation

- `GET /up` reports process liveness.
- `GET /health/ready` reports dependency readiness.
- Health responses are non-cacheable and do not expose dependency-level or release-identifying detail to public callers.
- Requests receive correlation identifiers, including request IDs and W3C trace context.
- Valid upstream trace context is preserved while the application creates a local request span/parent identifier.

## Queues and asynchronous work

Redis and Laravel Horizon provide queue processing. Meaningful domain changes that require asynchronous side effects use the transactional outbox.

Operational expectations:

- outbox publication is at-least-once;
- events carry stable idempotency keys;
- workers must tolerate retries;
- tenant context must propagate into queued work;
- publisher failures record bounded diagnostics and retry with bounded backoff;
- scheduler execution uses overlap/single-server protection where required.

Phase 3 reminders use this same durable boundary.

## Local operations

Common commands:

```bash
make up
make down
make check
make test
docker compose logs -f app worker
```

See [Getting Started](Getting-Started.md) and the [local development runbook](../runbooks/local-development.md).

## Backup and restore

The backup/restore baseline includes:

- backup before migrations when a populated PostgreSQL schema exists;
- compressed database dumps only after a successful dump;
- SHA-256 manifests;
- integrity verification before restore;
- collision-resistant temporary paths and owner-only file permissions;
- explicit failure when the matching manifest is absent/invalid unless an approved override is used;
- provenance tied to the existing running/stopped application release rather than the incoming deployment target.

Useful repository commands include:

```bash
make backup
CONFIRM_RESTORE=YES make restore FILE=backups/database-....sql.gz
```

Destructive recovery exercises are part of the operational evidence expected by the program.

## Deployment and runtime hardening

Hosted releases use immutable image/release metadata. Production runtime rejects unsafe configuration such as debug mode or insecure transport. Application roles run with restricted privileges, and runtime images are intentionally kept free of development tooling and unrelated repository content.

The web role receives read-only runtime storage; write access is limited to application roles that require it.

## Security and dependency operations

CI and release gates include dependency audits, Dependency Review, CodeQL, action pinning, image vulnerability scanning, test/static-analysis gates, and release metadata checks.

See [Security and Tenancy](Security-and-Tenancy.md) and the [security baseline](../SECURITY_BASELINE.md).

## Domain operations

- [Phase 3 operations](../PHASE_3_OPERATIONS.md) — events, reminders, rally operations, and troubleshooting.
- [Phase 4 operations](../PHASE_4_OPERATIONS.md) — recruitment jobs, intake, retention, and troubleshooting.
- [Incident response runbook](../runbooks/incident-response.md) — incident handling and escalation process.
- [Release checklist](../RELEASE_CHECKLIST.md) — release acceptance and deployment checks.

## Operational rule

Do not repair tenant-scoped workflow state through direct database edits when the application provides an auditable domain workflow. Diagnose through logs, correlation IDs, domain state, queue/outbox records, and the relevant operations runbook, then use supported actions whenever possible.
