# ADR-0032: Withdrawn account audit-reference lock change

Status: Withdrawn

## Context and correction

The proposed FOR NO KEY UPDATE account lifecycle barrier assumed that Alliance writers insert audit_events.actor_user_id after acquiring Alliance resources. That premise was incorrect: AllianceWriteState supplies a PlayerReference, whose auditUserId returns null and whose auditPlayerId preserves the domain actor. The actual Alliance operation does not take the claimed User foreign-key reference lock.

CI on ee943b7f executed all 1,239 PHP cases and reported four failures in the new account cleanup tests: each incorrectly expected the Alliance audit to reference the account instead of the Player. Both exclusive account-barrier cases passed. The claimed account/Alliance wait cycle was therefore unsupported by the actual owner contract.

## Decision

Withdraw the account lock change. AccountIdentityQuery.lockCurrent and lockActive retain FOR UPDATE, including current active-account validation. Existing account mutation and finalization owners keep their original locks. Preserve Player-based Alliance audit attribution and existing foreign keys.

The six added regression cases are corrected to verify the real contract: both account cleanup/Alliance writer orders, successful and rolled-back cleanup, Player audit attribution, terminal authority revocation and exclusion of competing account/ownership writers. Their corrected execution and containing gates remain recorded in the delivery ledger under HARD-073.

## Consequences

No new account lock protocol is introduced. A future lock change requires evidence from the actual referencing actor and owner path. General PostgreSQL lock compatibility alone does not establish a production wait cycle.
