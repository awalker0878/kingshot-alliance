# Release checklist

Status: Current

## Before build

- exact release commit selected;
- tests/static analysis/frontend build/security checks green;
- clean PostgreSQL installation succeeds through every migration;
- production JavaScript and stylesheet budgets green;
- migration and rollback/recovery impact reviewed;
- documentation updated for changed contracts/runbooks.

## Image

- immutable image built from exact commit;
- `APP_VERSION`/`RELEASE_SHA` metadata correct;
- image vulnerability/security checks acceptable;
- image digest recorded.

## Before deploy

- runtime configuration passes hosted validation;
- secrets injected from approved source;
- database backup prepared when required;
- production control evidence/owners identified.

## After deploy

- migrations successful;
- `/up` and `/health/ready` healthy;
- representative smoke checks pass;
- queues/Horizon/outbox/integrations observed;
- release evidence recorded;
- production status changed only through the governance approval record.

## Release evidence

The pull-request head must retain green results for PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture verification, dependency review, CodeQL, visual regression, production image build and scanning, ephemeral staging, and backup/restore. A rerun replaces earlier evidence only when it executes the same commit.
