# Audit domain

## Purpose

Owns attributable security/business audit-event recording for privileged and security-relevant changes without owning or authorizing the business state being changed.

## Owned code

Runtime code in this module owns persisted audit-event evidence and the supported recording service consumed by other domains.

## Public contracts

- Audit recorder for successful attributable transitions.
- Audit-event persistence/correlation used by authorized operational/security review.

Audit metadata must remain bounded and must not contain credentials, bearer tokens, recovery codes, private keys, or unnecessary private narrative fields.

## Dependencies

- `Identity` — actor/correlation identity when present.
- `Alliances` — tenant identity for Alliance-scoped evidence.
- Feature domains — safe action/target/context metadata for accepted transitions.

Audit does not depend on audit records to grant permission; owning domains authorize first.

## Canonical documentation

- [`docs/domains/audit/`](../../../docs/domains/audit/README.md)
