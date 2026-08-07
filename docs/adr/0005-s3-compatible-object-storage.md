# ADR 0005 — S3-compatible object storage abstraction

- **Status:** Accepted
- **Date:** 2026-08-06
- **Related phase:** Phase 0

## Context

Media, exports, and backups require durable object storage. The platform should avoid coupling domain code to one provider.

## Decision

Use Laravel filesystem abstractions and the S3 driver. Domain code requests storage through application interfaces. Objects are private by default, use alliance-prefixed paths after Phase 1, and record ownership and lifecycle metadata in PostgreSQL.

Local development uses the local disk. Hosted environments may use an approved S3-compatible service with encryption, versioning, retention, and access logging.

## Consequences

Provider portability improves, while provider-specific features require explicit adapters and review.

## Validation

Storage contract tests will cover write, read, delete, temporary access, tenant path isolation, and provider failure.
