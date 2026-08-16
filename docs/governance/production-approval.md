# Production approval

**Decision: Not yet approved for a real production cutover**  
**Repository/application hardening: accepted baseline exists**  
**Real production evidence: pending**

This is the authoritative production go/no-go record in the live documentation structure.

## Required release evidence

Before approval, record non-secret evidence for the actual production release candidate and deployed environment:

- exact release commit SHA;
- immutable image digest and revision/version metadata;
- protected CI/security results for the exact release;
- deployed configuration/launch-check result;
- staging/production smoke evidence;
- backup/recovery evidence identifier.

## External controls

| Control | Required evidence | Status |
| --- | --- | --- |
| HTTPS/ingress | Certificate and ingress validation | Pending |
| Trusted proxies | Approved proxy boundary/configuration | Pending |
| Webhook egress | Network-policy test blocks metadata/private/management destinations | Pending |
| Capacity | PostgreSQL, Redis, worker and storage sizing/review | Pending |
| Alerting | Alert destinations, escalation ownership and test | Pending |
| Recovery | Database + private media + application-key recovery exercise | Pending |
| Platform administrators | At least two operational accounts meeting required account assurance | Pending |
| External dependencies | DNS, mail, object storage, secret management and backup ownership | Pending |
| Support/change ownership | Named launch/change owner and incident/support coverage | Pending |

## Approval rule

Production is a no-go while any required control is Pending, required protected checks are not green for the exact release, or deployed launch/configuration checks fail.

CI success never auto-approves production because infrastructure and accountable operational controls cannot be inferred from repository state.