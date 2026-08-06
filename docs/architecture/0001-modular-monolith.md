# ADR 0001 — Enterprise modular monolith

- **Status:** Accepted
- **Date:** 2026-08-06
- **Related phase:** Phase 0

## Context

The platform must support several business capabilities and multiple alliances without imposing distributed-system cost before scale and team boundaries justify it.

## Decision

Build one Laravel deployment with explicit domain modules, a shared relational database, thin delivery adapters, and dependency-injected application services. Domains may communicate through public application interfaces and domain events, not direct access to another domain's internals.

Extraction to a service requires measured scaling, isolation, ownership, or release-cadence pressure and a documented ADR.

## Consequences

The system remains operationally simple and transactionally strong. Discipline is required to prevent the codebase from becoming an unstructured monolith.

## Validation

Architecture review, dependency tests, namespace rules, and module documentation will verify boundaries.
