# ADR-0060: Atomic external participation scope order

Status: Accepted

## Context

External Event participation held an integration credential and actor link before calling the Operations owner, which then acquired current Player and Alliance scope. Credential and link revocation acquired Alliance scope before those integration rows. Concurrent requests could form a lock cycle. Moving the scope read outside a transaction would release its protection before the write, while copying Event authorization into Integrations would create another authority.

## Decision

ExecuteExternalEventParticipation may compose its existing owner calls in one database transaction, as an explicit named exception to the Workflow transaction rule. It first calls the Operations-owned LockEventParticipationScope with scalar actor and occurrence identities. Operations reacquires the current Event target, identity, membership, authorization, verified participation profile and occurrence locks through the same EventParticipationWriteState used by normal response, registration and cancellation Actions. No owner models, SQL or permission interpretation enter the Workflow.

Integrations then reacquires the current credential and verified link, checks runtime availability and scope, and owns the idempotency receipt around the normal Participation Action. That Action retains all response, capacity and waitlist semantics. A failure anywhere rolls back participation, receipt, audit and outbox together. Replays also recheck the current Operations scope before returning a stored response.

Pairing claims can hold credential/link rows before inserting foreign-key references to Player/Alliance. The external action therefore acquires those lower integration locks with PostgreSQL NOWAIT. Contention aborts the whole composition and produces a generic HTTP 409 with Retry-After: 1. Clients retry the same request and idempotency key. The integration exception contains no credential or provider details in the HTTP response. This also avoids waiting behind another external request while holding the Event scope.

The existing verified profile gate currently enables participation only for Alliance Bear Hunt. Kingdom and personal Event profiles remain subject to their existing Operations gate; this change does not enable new Event workflows or reinterpret their permissions. Future profile enablement must verify its complete lock composition.

The architecture verifier names this one additional Workflow Action. All other Workflow transaction prohibitions, direct persistence/model/lock prohibitions, read-only ReadModels and thin HTTP rules remain enforced. NotificationDelivery gains no transaction authority.

## Verification

ExternalActorScopeOrderingTest uses two real PostgreSQL connections and owner Actions for credential/link revocation in both orders against response and registration. It also covers a competing pairing claim, HTTP conflict and same-key retry, replay after membership removal, and an injected late receipt failure proving complete rollback and duplicate-free retry. The normal Participation owner suite remains part of containing verification. HARD-116 records executed evidence separately from authored coverage.
