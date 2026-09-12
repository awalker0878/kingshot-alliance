# ADR-0071: Event secondary references and current Rally guidance scope

Status: Accepted

## Context

Event admission now acquires the governing owner scope before its actor and Event rows. Several subcommands subsequently acquired additional Governor or Alliance locks with an ordinary wait. A second owner could already hold that lower reference while waiting for the Event actor or scope. Rally guidance also locked its actor before the Alliance and did not hold active Kingdom admission. The remaining caller trace therefore needed the same lifecycle/authority and contention treatment as Territory linked references.

## Decision

Operations keeps its command transactions and authorization. Rally guidance acquires Alliance, active Kingdom, current Alliance authority and canonical actor in that order, then rechecks the actor's Kingdom before recording the rule. Authority withdrawal and Kingdom archive therefore serialize with the complete command.

EventLinkedReferenceState obtains secondary references through the owning Alliance/GameWorld NOWAIT queries. Rally, roster, battle-assignment and result commands use it after protected Event admission. Contention on these secondary references becomes an actionable validation error, rolling back the complete Event command and allowing retry after the owner change finishes. Other database errors retain their original meaning. The owning reference queries continue to reject noncanonical identities; each caller retains its target, roster and profile checks.

Moving secondary locks before Event admission would reacquire governing scope after lower identities and recreate the same cycle. Catching database deadlocks would allow avoidable waits and does not provide a predictable contention boundary. NOWAIT is limited to these lower references; governing owner locks retain their ordinary serialization behavior. No cross-owner model or permission vocabulary moves into Operations.

Only the verified Alliance Bear Hunt profile currently enables the specialized roster, Rally and result workflows. Candidate Kingdom and battle-assignment profiles remain disabled. Changing lock acquisition does not enable those profiles or imply that they have been behaviorally exercised as supported workflows.

## Verification

EventLinkedReferenceContentionTest runs four enabled Bear Hunt commands against an actual competing Governor lock, asserts complete effect/audit/outbox rollback and retries successfully. RallyGuidanceScopeOrderingTest runs actual rank withdrawal and Kingdom archive in both orders, verifies retained scope lock ordering, and checks rejected writes leave no guidance/audit/outbox effect. Existing profile boundaries and complete Operations regression remain required. HARD-129 records executed evidence.
