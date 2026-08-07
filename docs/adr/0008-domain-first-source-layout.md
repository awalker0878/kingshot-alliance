# ADR 0008 — Domain-first source layout

- **Status:** Accepted
- **Date:** 2026-08-07
- **Related phases:** Phase 0 foundation; Phase 1–4 integration alignment

## Context

The implementation plan defines a domain-first repository layout with all runtime application code owned beneath `app/Domain/<Domain>`. During Phases 0–4, Laravel's default `app/Models` folder and parallel `app/Application`, `app/Http`, `app/Infrastructure`, and `app/Providers` trees accumulated beside a smaller `app/Domain` tree. That physical layout obscured ownership and allowed business capabilities such as alliances, memberships, authorization, reminders, and rallies to drift into broader buckets.

An older architecture README also showed the parallel layer-first folders. That example conflicted with the implementation plan's canonical repository structure.

## Decision

The implementation plan's domain-first tree is authoritative.

All runtime PHP under `app/` is owned beneath one of the canonical domains. Framework adapters and implementation details remain explicit, but they are nested inside the domain that owns them, for example `Domain/Content/Actions`, `Domain/Events/Http`, or `Domain/Platform/Providers`.

The canonical domains are Alliances, Audit, Authorization, Content, Contributions, Events, Identity, Integrations, Kingdoms, Memberships, Notifications, Platform, Rallies, and Recruitment. A future-phase domain may exist as documentation only, but must not contain runtime PHP before its approved phase.

Documentation and tests follow the corresponding canonical groups from the implementation plan. No compatibility aliases or duplicate legacy source trees are retained because the application is not yet in production.

## Consequences

Ownership is visible from the filesystem and namespace. Domain reviews can identify cross-domain dependencies directly. Laravel conventions that infer classes from namespaces, such as model factories, must be configured explicitly when the domain-first location changes the framework default.

Cross-domain calls are not prohibited, but they must use intentional supported contracts. Persistence-model imports across domain boundaries are treated as a review signal and should be removed when they expose another domain's private implementation.

## Validation

Architecture tests verify the canonical domain directories, the absence of the superseded top-level application layers, documentation/test group presence, and the absence of runtime PHP in future-phase domains. CI, PHPStan, PHPUnit, CodeQL, dependency review, staging, recovery, and image scanning remain required before merge.
