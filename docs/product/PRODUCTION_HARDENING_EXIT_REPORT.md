# Production Hardening Exit Report

**Status:** Candidate — protected validation pending  
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

## Final gate

This report remains **Candidate** until the exact PR head passes formatting, PHPStan, the complete backend/frontend test suites, PostgreSQL migration, dependency review, CodeQL, immutable-image build, ephemeral staging deployment, backup/restore, and image scanning. Once those gates are green and review/hygiene checks are clean, this report may be updated to **Accepted** before merge.
