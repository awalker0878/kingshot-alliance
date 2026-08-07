# Incident Response Runbook

## Severity

- **SEV-1:** active compromise, broad data exposure, destructive corruption, or full outage
- **SEV-2:** material security or availability degradation with limited workaround
- **SEV-3:** contained degradation or defect without immediate material risk

## First response

1. Assign incident commander and scribe.
2. Record detection time, release SHA, environment, request IDs, and affected alliances.
3. Preserve logs and evidence.
4. Contain access, traffic, queue processing, integrations, or releases as appropriate.
5. Communicate known facts and next decision time.
6. Avoid destructive investigation commands.

## Investigation

Correlate HTTP requests, logs, jobs, and integrations using request and trace IDs. Determine impact boundaries before querying or exporting tenant data.

## Recovery

Use the deployment, rollback, and backup runbooks. Validate liveness, readiness, key workflows, worker health, and data integrity.

## Close

Document timeline, root causes, contributing conditions, impact, remediation, detection gaps, and owners. Convert lessons into tests, alerts, runbooks, and ADR updates.
