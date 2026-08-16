# Deployment runbook

Status: Current

## Preconditions

- exact release SHA selected;
- protected checks green for that release;
- immutable image built and digest recorded;
- configuration/secrets supplied through approved runtime mechanisms;
- migration/recovery impact reviewed;
- production approval evidence requirements understood.

## Procedure

1. run repository release/configuration checks;
2. create/verify database backup before migrations when the database is populated;
3. execute the repository deployment path (`bin/deploy` or environment automation that preserves its release invariants);
4. apply migrations from the intended release image;
5. deploy web and worker processes;
6. verify `/up` and `/health/ready`;
7. run smoke checks for authentication and representative scoped functionality;
8. verify queues/Horizon/outbox publication and error rate;
9. record release SHA, image digest, validation evidence and operator/change owner.

## Abort conditions

Abort/rollback when release identity is inconsistent, migrations fail, readiness fails, critical background work is not processing, or the deployed environment violates a required production control.

A successful technical deployment does not automatically change production approval to Approved.