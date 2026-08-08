# Production Launch Runbook

## Purpose

This runbook is the post-Phase-6 production-hardening gate described by `docs/product/IMPLEMENTATION_PLAN.md`. It does not create a new product phase. It converts launch-readiness requirements into repeatable operator checks and records which controls remain infrastructure or organizational responsibilities.

## Preconditions

Before approving a production launch:

- deploy an immutable image by digest from a protected, green commit;
- configure `APP_ENV=production`, HTTPS, secure cookies, trusted proxies, PostgreSQL TLS, Redis-backed cache/queues/sessions, and private S3-backed media;
- configure the Phase 6 Horizon `core`, `integrations`, and `maintenance` partitions;
- create at least two active platform-administrator grants, each backed by a verified account with confirmed MFA;
- configure egress controls so webhook workers cannot reach metadata, loopback, private, or management networks even if DNS changes after application validation;
- validate database backup, private-media backup, and application-key recovery as one recovery set;
- assign owners for readiness, queue/job, webhook, database, and storage-capacity alerts.

## Repository-controlled launch gate

Run from the deployment host with the same Compose file and protected environment file used by the deployment:

```sh
COMPOSE_FILE=docker-compose.staging.yml \
ENV_FILE=deploy/production.env \
APP_PUBLIC_URL=https://alliance.example.com \
sh bin/launch-check
```

The script requires owner-only permissions on the environment file, verifies the `app`, `web`, `worker`, and `scheduler` services are running, executes `app:config-check`, executes `app:launch-check`, and optionally verifies `/up`, `/health/ready`, and the unauthenticated `/platform` authentication boundary.

`php artisan app:launch-check --json` is available for automation and evidence capture.

## Launch-check policy

The application gate fails closed when any repository-controlled prerequisite is unhealthy:

- production runtime configuration does not meet hosted security/durability rules;
- fewer than the configured minimum active platform administrators exist;
- any active platform administrator lacks verified email or confirmed MFA;
- an active alliance is missing platform settings;
- transactional-outbox messages remain unpublished beyond the configured grace period and threshold;
- failed queue jobs exceed the configured threshold;
- recent failed webhook deliveries exceed the configured threshold.

Thresholds are configured with `LAUNCH_*` environment variables documented in `.env.example`. Defaults are intentionally conservative and should only be relaxed through an explicit operational decision with recorded rationale.

## Required external evidence

The repository cannot prove the following controls by itself. Record evidence before production approval:

1. HTTPS certificate, ingress/load-balancer, and trusted-proxy configuration.
2. Network egress policy for integration/webhook workers.
3. Database, object-storage, Redis, and host capacity sizing for the initial launch cohort.
4. Alert destinations, escalation ownership, and on-call/support coverage.
5. Database + private-media + application-key recovery exercise results and measured recovery time.
6. At least two named operational accounts with MFA-backed platform-admin eligibility.
7. Production DNS, email-delivery, object-storage, secret-management, and backup-provider ownership.

Do not commit account names, secrets, private addresses, recovery keys, or provider credentials. Store sensitive evidence in the approved operational system and reference only the evidence identifier in the launch approval record.

## Go/no-go

A production go decision requires all protected repository gates green on the exact release commit, `bin/launch-check` passing against the production deployment, and every external control above assigned and evidenced. Any bypass of MFA, immutable-image deployment, webhook egress controls, backup/key recovery, or protected CI is a no-go condition.
