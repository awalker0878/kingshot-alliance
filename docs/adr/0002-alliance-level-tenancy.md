# ADR 0002 — Alliance-level tenancy with global users

- **Status:** Accepted
- **Date:** 2026-08-06
- **Related phase:** Phase 0 design; Phase 1 implementation

## Context

A person may participate in several alliances. Alliance data must remain isolated while identity, account recovery, and platform administration operate globally.

## Decision

Use one global user identity and explicit alliance memberships. Alliance-owned rows carry a non-null alliance identifier. Active alliance context is resolved at the request boundary and must be propagated through authorization, queries, jobs, notifications, caches, exports, logs, and storage paths.

The application fails closed when alliance context is absent or inconsistent. Policies and scoped query objects are authoritative; UI filtering is never a security boundary.

## Consequences

Cross-alliance participation is supported without duplicate accounts. Every feature must prove isolation, increasing test and review obligations.

## Validation

Phase 1 must include adversarial cross-alliance read, write, route-binding, cache, queue, export, and storage tests.
