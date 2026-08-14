# Rallies security profile

[← Rallies domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Rallies  
**Code owner:** `app/Domain/Rallies`  
**Primary security boundary:** Authoritative Player ownership for self actions plus exact Event/Alliance authorization for Rally operations

## 1. Security purpose and scope

Protect Player-owned formations and private Rally planning from cross-Player, cross-Alliance, cross-Kingdom and cross-Event access.

## 2. Assets and sensitive data

Assets include Player formations, strategy guidance, hero recommendations, occurrence plans, group assignments, responses and participation evidence.

## 3. Actors, authentication and authorization

Self actors must own the active Player through `players.user_id`. Event managers require exact Event manage permission. Alliance guidance requires exact Alliance Event-manage permission. Active Player Context is never an Alliance/Kingdom privilege source.

## 4. Tenant and privacy boundaries

Rally Alliance is explicit and validated for the Event. Kingdom events may contain multiple Alliance plans, but each Player assignment must match the exact operating Alliance and current Kingdom/roster eligibility.

## 5. Trust boundaries and data flows

Route identifiers are untrusted. Controllers re-resolve occurrence/group/assignment/formation; actions repeat capability, authorization and eligibility checks before mutation.

## 6. Threats, abuse cases and controls

Controls address Player impersonation, forged Alliance context, cross-Kingdom assignment, double group assignment, lead/slot collision, capacity bypass and stale roster context through server-owned Player Context, exact target authorization, database constraints and transactional locks.

## 7. Integrity, concurrency and idempotency

Occurrence/group row locks serialize assignment operations. Partial unique indexes enforce active lead and slot uniqueness. Reconfirmation rechecks capacity and conflicts. Removed/declined rows release capacity without deleting evidence.

## 8. Secrets and credential handling

Rally fields contain no credentials. Free-text fields must not contain secrets or authentication tokens.

## 9. Destructive operations, retention and deletion

Deleting a Player formation is owner-only. Rally assignment removal is a status transition with actor evidence. Parent Event/Alliance deletion follows database foreign-key lifecycle rules.

## 10. Auditability, observability and evidence

Mutations record actor User, actor Player when available, Event/occurrence, operating Alliance, group/Player IDs, role/status and scope-aware outbox partition.

## 11. Residual risks and explicit non-capabilities

Rallies does not verify game-server execution and does not grant permissions from Rally roles or Player selection.

## 12. Focused reviews and related documentation

See [security baseline](../../../security/security-baseline.md), [Rallies domain](../README.md), [interfaces](../interfaces/README.md), and [testing](../testing/README.md).
