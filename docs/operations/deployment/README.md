# Deployment

Status: Current

Deploy immutable images built from the exact release commit. The repository `Dockerfile`, `bin/deploy`, hosted configuration validator and launch-check logic are the executable sources of truth.

## Deployment sequence

1. select the exact release commit;
2. complete protected quality/security checks;
3. build the immutable production image with version/revision metadata;
4. record the image digest;
5. validate hosted configuration without exposing secrets;
6. back up a populated database before schema-changing release work;
7. run migrations through the controlled release path;
8. deploy web/worker runtime using the immutable image;
9. run readiness and smoke checks;
10. verify background processing/outbox health;
11. capture release evidence;
12. apply [Production approval](../../governance/production-approval.md) before declaring a real production cutover approved.

For the hosted target, see [Azure/container deployment](azure.md).

Rollback and restore are different operations. Roll back application release when the prior code/schema remains compatible; use database restore only when the recovery decision explicitly requires destructive data restoration.