# Incident response runbook

Status: Current

## Triage

1. identify user impact and affected environment/release;
2. capture request/trace IDs and safe structured diagnostics;
3. check liveness/readiness, database, Redis, queue/outbox backlog and recent deployment changes;
4. contain active harm before attempting optimization;
5. decide whether the safest action is configuration correction, worker recovery, release rollback or disaster recovery.

## Security incidents

Preserve evidence, rotate/revoke affected credentials through the approved secret/identity mechanisms and avoid copying sensitive payloads into GitHub issues or documentation.

## Recovery

Use the narrowest safe recovery action. Do not restore the database merely to fix an application error when rollback is sufficient.

## Closeout

Record impact, root cause, corrective action, relevant release IDs and follow-up ownership. Update a runbook or architecture/governance rule when the incident exposed a documentation or control gap.