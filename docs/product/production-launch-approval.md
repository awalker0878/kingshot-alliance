# Production Launch Approval

**Decision:** Not yet approved for a real production cutover  
**Repository hardening:** Accepted and merged  
**Production evidence:** Pending

## Repository hardening evidence

The post-Phase-6 repository-controlled hardening stage was accepted on validated PR head `c81c40b619750407cebffd6c8ff77597e049a242` and squash-merged to `main` as `dfc608c7fdc665a82eb74238fe2fc123755f37c7`.

That validated head passed:

- PostgreSQL migration;
- Pint and PHPStan;
- the complete backend suite (185 tests / 1,555 assertions);
- frontend checks;
- Dependency Review;
- CodeQL;
- immutable production-image build;
- ephemeral staging deployment;
- backup/restore demonstration;
- image vulnerability scanning.

This evidence establishes repository hardening only. It is not evidence that a real production environment is configured, healthy, or approved.

## Production release evidence required

Before this record can be changed to `Approved`, record evidence from the actual production release candidate and deployed production environment:

- release commit SHA;
- immutable image digest and OCI revision/version labels;
- protected CI result for the release commit;
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

Production launch is a **no-go** while any row remains Pending, while protected checks are not green on the exact production release commit, or while `app:launch-check` fails against the deployed production database and configuration.

This document intentionally does not auto-approve production from CI. The implementation plan requires accountable owners to accept launch risks, and infrastructure controls cannot be truthfully inferred from repository state.
