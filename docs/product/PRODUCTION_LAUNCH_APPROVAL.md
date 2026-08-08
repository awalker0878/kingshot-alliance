# Production Launch Approval

**Decision:** Not yet approved for a real production cutover  
**Repository state:** Candidate pending protected validation

## Repository evidence required

Before this record can be changed to `Approved`, record:

- release commit SHA;
- immutable image digest and OCI revision/version labels;
- protected CI result;
- CodeQL result;
- Dependency Review result;
- image-scan result;
- `app:launch-check --json` output captured from the production deployment;
- staging and production smoke-test evidence;
- backup/restore evidence identifier.

## External control approvals required

The deployment owner must record non-secret evidence identifiers for each item below. Sensitive details must remain in the approved operational system, not this repository.

| Control | Required evidence | Status |
|---|---|---|
| HTTPS and ingress | Certificate/ingress validation record | Pending |
| Trusted proxies | Approved proxy boundary/configuration record | Pending |
| Webhook egress | Network policy test showing metadata/private/management destinations are unreachable | Pending |
| Capacity | Database, Redis, worker, and storage sizing review for launch cohort | Pending |
| Alerting | Alert destinations, escalation owners, and test notification record | Pending |
| Recovery | Database + private media + application-key recovery exercise | Pending |
| Platform administrators | At least two operational accounts with verified email and MFA | Pending |
| Production dependencies | DNS, mail, object storage, secret management, and backup ownership | Pending |
| Support | Named launch/change owner and incident/support coverage | Pending |

## Approval rule

Production launch is a **no-go** while any row remains Pending, while protected checks are not green on the exact release commit, or while `app:launch-check` fails against the deployed production database and configuration.

This document intentionally does not auto-approve production from CI. The implementation plan requires accountable owners to accept launch risks, and infrastructure controls cannot be truthfully inferred from repository state.
