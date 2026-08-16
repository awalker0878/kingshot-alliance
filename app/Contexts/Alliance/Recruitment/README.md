# Recruitment domain

## Purpose

Owns Alliance recruitment intake, application questions/settings, candidate pipeline/review/decisions, controlled onboarding handoff, metrics, and unsuccessful-candidate retention/anonymization.

## Owned code

Runtime code in this module owns Recruitment settings/questions/candidates/answers/private review state, decision/onboarding/retention workflows, and recruiter/public application surfaces.

## Public contracts

- authoritative public/invitation-only recruitment availability/configuration;
- private recruiter candidate-management workflow under `recruitment.manage`; and
- accepted-candidate handoff into the supported Memberships invitation contract.

## Dependencies

- `Alliances` — active tenant/public Alliance context.
- `Authorization` — `recruitment.manage`.
- `Memberships` — controlled membership invitation/onboarding transition.
- `Identity` — actor/password assurance.
- `Audit` / Platform outbox — privileged/durable evidence.

Content may display Recruitment availability but does not own a duplicate writable recruitment-status field.

## Canonical documentation

- [`docs/domains/recruitment/`](../../../docs/domains/recruitment/README.md)
