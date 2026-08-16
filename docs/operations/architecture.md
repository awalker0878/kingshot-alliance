# Runtime architecture

Status: Current

The hosted application is designed as an immutable containerized Laravel deployment backed by PostgreSQL and Redis, with durable private media stored in S3-compatible object storage for production.

```text
Client
  -> HTTPS ingress
      -> web container(s)
          -> PostgreSQL
          -> Redis
          -> private object storage
          -> queue/outbox work
      -> worker/Horizon container(s)
      -> scheduler/maintenance execution
```

## Core dependencies

- **PostgreSQL** — authoritative relational state.
- **Redis** — hosted cache, sessions, queues, Horizon and coordination locks.
- **Object storage** — durable production private/content media.
- **Mail provider** — external dependency for real email delivery.
- **Ingress/TLS** — externally managed HTTPS boundary.

## Health

- `GET /up` is process liveness.
- `GET /health/ready` checks readiness dependencies without exposing sensitive dependency details.

## Release identity

Hosted releases declare immutable application version and 40-character Git release SHA. Production release identity should be tied to an immutable image digest/OCI metadata rather than a mutable image tag alone.

## Evidence boundary

Repository tests can prove application/runtime checks. They cannot prove that a real environment has correct ingress, trusted proxies, egress policy, alert routing, capacity, DNS/mail/object-storage ownership or operational staffing. Those remain production-approval evidence.