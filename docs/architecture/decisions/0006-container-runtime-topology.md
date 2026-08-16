# ADR 0006: Containerized hosted runtime

Status: Accepted

## Decision

Operate the hosted application as immutable container releases with PostgreSQL as the relational database and Redis for hosted cache, queues and sessions. Production content media uses durable S3-compatible storage. Repository health/configuration/launch checks gate deployability, while real production approval also requires infrastructure evidence.

## Rationale

The model supports reproducible releases, independent web/worker scaling, durable data services, explicit health checks and recovery procedures.

## Consequences

Hosted configuration fails closed when required controls are absent. Deployment identity is tied to immutable release metadata/digests rather than mutable tags alone. CI evidence does not by itself approve the real production environment.