# Kingshot Alliance Wiki

Welcome to the source-controlled wiki for **Kingshot Alliance**, an enterprise-ready, multi-alliance coordination platform for the Kingshot community.

The wiki is an orientation layer over the repository's canonical documentation. Detailed implementation plans, ADRs, threat models, phase exit reports, runbooks, and domain guides remain authoritative; wiki pages summarize and link to them rather than replacing them.

## Current product status

Phases **0 through 4** are implemented and aligned as an integrated product. The next unimplemented product phase is **Phase 5 — Contributions and reporting**.

| Phase | Capability | Status |
|---|---|---|
| 0 | Engineering foundation | Complete |
| 1 | Identity and multi-tenancy | Complete |
| 2 | Content and public presence | Complete |
| 3 | Events and rallies | Complete |
| 4 | Recruitment | Complete |
| 5 | Contributions and reporting | Next |
| 6 | Platform scale and administration | Planned |

See [Roadmap and Phases](Roadmap-and-Phases.md) for the phase model and the integrated Phase 1–4 ownership boundaries.

## Start here

- [Getting Started](Getting-Started.md) — local setup, services, commands, and development workflow.
- [Architecture](Architecture.md) — modular-monolith structure, domain boundaries, tenancy, and integration principles.
- [Security and Tenancy](Security-and-Tenancy.md) — authentication, authorization, tenant isolation, privileged actions, and data protection.
- [Roadmap and Phases](Roadmap-and-Phases.md) — delivery phases, current status, and domain ownership.
- [Events and Rallies](Events-and-Rallies.md) — event scheduling, registration, reminders, formations, rally coordination, and attendance.
- [Recruitment](Recruitment.md) — intake, candidate pipeline, decisions, onboarding, metrics, and retention.
- [Operations](Operations.md) — health, queues, observability, backup/restore, deployment, and troubleshooting references.

## Product vision

Kingshot Alliance provides a secure shared system for alliances to manage their public presence, members, events, rallies, recruitment, communications, contributions, and operational reporting. It is designed to replace fragmented chat messages, spreadsheets, screenshots, and manual reminders with a tenant-safe operational platform.

## Technology baseline

- PHP 8.5 and Laravel 13
- Inertia 3, Vue 3, TypeScript, Tailwind CSS 4, and Vite 8
- PostgreSQL 18
- Redis 8 with Laravel Horizon
- Laravel Pulse, Pennant, and Sanctum foundations
- Docker Compose for local development
- GitHub Actions for quality, security, test, and image validation

## Canonical documentation

- [Program implementation plan](../IMPLEMENTATION_PLAN.md)
- [Phases 1–4 alignment audit](../PHASES_1_4_ALIGNMENT_AUDIT.md)
- [Architecture decisions](../architecture/README.md)
- [Security baseline](../SECURITY_BASELINE.md)
- [Definition of done](../DEFINITION_OF_DONE.md)
- [Release checklist](../RELEASE_CHECKLIST.md)
- [Events and rallies guide](../EVENTS_AND_RALLIES.md)
- [Recruitment guide](../RECRUITMENT.md)
- [Repository contributing guide](../../CONTRIBUTING.md)

## Documentation rule

When a wiki summary and a canonical repository document disagree, treat the canonical document, accepted phase exit report, and most recent integration audit as the source of truth. Update the wiki in the same change that modifies a user-facing workflow or an architectural boundary.
