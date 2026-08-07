# Phase 1 Migration and Rollback Strategy

**Phase:** Identity and Multi-Tenancy  
**Database:** PostgreSQL 18 in hosted/CI environments

## Migration shape

Phase 1 introduces additive identity and tenancy tables on top of the accepted Phase 0 schema:

- `users`
- `alliances`
- `alliance_memberships`
- `permissions`
- `roles`
- `role_permissions`
- `membership_roles`
- `invitations`
- `personal_access_tokens`
- `audit_events`
- `outbox_messages`

The Phase 1 application has not been released to production before this phase exit, so these migrations remain the canonical initial definitions rather than requiring expand-and-contract compatibility migrations for already-populated Phase 1 tables.

## Integrity strategy

- Alliance-owned identity rows use alliance foreign keys.
- `membership_roles` uses composite tenant foreign keys so a membership and role must belong to the same alliance.
- User email, alliance slug, permission key, invitation token hash, and outbox idempotency keys carry uniqueness constraints appropriate to their scope.
- Foreign-key delete behavior is explicit so identity and audit relationships do not silently orphan privileged state.
- Meaningful state transitions write audit/outbox rows in the same transaction as the domain mutation.

## Forward deployment

1. Build the immutable application image from the reviewed commit.
2. Validate runtime configuration before migration.
3. Back up the existing PostgreSQL database using the Phase 0 verified backup process.
4. Run Laravel migrations as the controlled release step.
5. Start the new application roles from the same immutable image digest.
6. Verify `/up` and `/health/ready` plus the identity smoke path in staging.
7. Confirm queue/scheduler health; the outbox publisher is safe to retry and uses message idempotency keys.

The CI `Container, staging, and recovery` job executes the production-image deployment path and destructive backup/restore exercise for the phase branch.

## Application rollback

The normal operational rollback remains **application-image rollback without reversing database migrations**:

- Deploy the previous known-good image digest using `bin/rollback`.
- Do not automatically reverse Phase 1 migrations during an application rollback.
- Phase 0 code does not depend on or mutate the new Phase 1 tables, so leaving the additive tables in place is compatible with an application rollback to the accepted Phase 0 image.
- Preserve the Phase 1 database state for diagnosis and forward-fix unless an explicit data rollback is approved.

This follows the repository's existing rule that application rollback must not assume database migrations are safely reversible after users have written data.

## Database rollback

Laravel `down()` methods exist for development/test reset and for a controlled pre-release rollback. Once real Phase 1 identity data exists, dropping these tables is destructive and is **not** the standard production rollback path.

If a database-level rollback is explicitly required after data exists:

1. Stop writes and capture a fresh verified backup.
2. Record the running release SHA/image digest and affected migration batch.
3. Prefer a forward corrective migration when possible.
4. If destructive rollback is approved, restore the verified pre-migration backup rather than relying on partial table drops.
5. Re-run readiness checks and reconcile any external side effects using outbox idempotency keys.

## Outbox compatibility

`outbox_messages` is part of the transactional persistence boundary. A message is not considered published until `published_at` is set. Failed publication records `attempts`, a future `available_at`, and bounded `last_error` diagnostics. Consumers receive the unique `idempotency_key` and must treat delivery as at-least-once.

## Evidence

- PostgreSQL migrations pass in CI.
- Tenant composite foreign-key behavior is exercised by adversarial tests.
- The complete Phase 1 test suite runs against PostgreSQL in CI.
- The immutable staging deployment and destructive backup/restore job pass before acceptance.
