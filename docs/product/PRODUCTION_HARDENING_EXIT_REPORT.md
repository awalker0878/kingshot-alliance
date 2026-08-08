# Production Hardening Exit Report

**Status:** Accepted  
**Stage:** Post-Phase-6 production hardening

## Scope

The implementation plan ends at Phase 6 and explicitly calls for production hardening and launch approval afterward. This stage adds no new product domain or future-phase capability. It converts launch-readiness expectations into fail-closed operational checks and release evidence.

## Repository-controlled hardening

- `app:launch-check` validates production runtime configuration and operational readiness.
- Launch readiness requires redundant platform administration and verifies verified-email/MFA protection for every active platform administrator.
- Active alliances must have their Phase 6 platform settings provisioned.
- Transactional-outbox backlog, failed queue jobs, and recent webhook-delivery failures are checked against explicit launch thresholds.
- `.env.example` documents the launch thresholds so changes are visible and reviewable.
- `bin/launch-check` verifies the running application stack and optionally probes public liveness/readiness and the `/platform` authentication boundary.
- `docs/operations/PRODUCTION_LAUNCH_RUNBOOK.md` separates repository-verifiable controls from infrastructure/process evidence that must be supplied by the deployment owner.

## Explicit non-go controls outside the repository

Production approval still requires external evidence for HTTPS/ingress, trusted proxies, webhook egress restrictions, capacity sizing, alert routing/ownership, database + private-media + application-key recovery, operational administrator ownership, production DNS/email/storage/secrets, and support/on-call arrangements.

The application must not represent those external controls as complete merely because CI passes.

## Verification added

Automated tests cover:

- fail-closed launch status when platform-administrator redundancy is missing;
- successful repository-controlled launch readiness when production configuration, MFA-backed administrators, queues, outbox, and integrations are healthy.

## Acceptance evidence

Protected validation passed on implementation head `8ff6b63253f768705c51566ea035ce680d0fe034`:

- PostgreSQL migration;
- PHP formatting and PHPStan;
- complete backend suite: 185 tests / 1,555 assertions;
- complete frontend checks;
- Dependency Review;
- CodeQL;
- immutable production-image build;
- ephemeral staging deployment;
- backup/restore demonstration;
- image vulnerability scan.

Review/hygiene inspection found no unresolved review threads and no temporary workflow or diagnostic artifacts in the PR diff.

## Acceptance decision

Repository-controlled production hardening is **Accepted**. This acceptance does **not** approve a real production cutover. `docs/product/PRODUCTION_LAUNCH_APPROVAL.md` remains the authoritative go/no-go record for external production controls and must remain unapproved until those controls are evidenced by accountable operators.
