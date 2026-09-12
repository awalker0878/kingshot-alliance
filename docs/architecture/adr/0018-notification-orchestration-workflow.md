# ADR-0018: Own cross-context notification orchestration in a Workflow

Status: Accepted

Date: 2026-09-08

## Previous architecture and problem

Officer Brief and Intelligence change queues, publishers, CLI adapters and execution-result DTOs lived under ReadModels. They composed authorized reads but also created Communications intent and advanced sweep cursors. This conflicted with the read-only ReadModel contract. ADR-0016 described read-model Actions as valid command owners, and architecture checks only detected direct persistence, leaving indirect owner writes unprotected.

## Alternatives and decision

Keeping the writers in ReadModels would weaken the read-only contract. Moving them to Communications would make generic delivery own Alliance/Intelligence/Operations meaning. Adding a new bounded context would create unnecessary domain authority.

`Workflows/NotificationDelivery` owns the existing cross-context queue orchestration, publishers, commands and `NotificationQueueSweep` execution result. Its provider registers both notification sweep commands. Remove the superseded ReadModel writers/providers and migrate all callers and behavioral tests; retain no aliases.

`ReadModels/CommandOverview`, `IntelligenceSignals` and `NotificationDelivery` retain authorized fact/recipient projections. Communications remains the only owner of delivery/preferences/attempt state. The workflow persists no brief/signal truth or domain aggregates and owns no database transactions.

## Authorization contracts

Workflows call explicit semantic read-authorization methods on the existing owner services: membership/recruitment management, Intelligence view, transfer view and Alliance event view. Those methods delegate to the same owner permission implementation. This exposes supported owner operations without importing permission enums into the workflow or creating another authorization evaluator. Existing current-recipient ownership and authorization checks remain immediately before publication.

## Scalability and operations

Keep bounded membership pages, cursor cycling, local-day cadence, semantic fingerprints and Communications idempotency. The move does not claim to resolve all query budgets, delivery-time revocation or retry concerns; those remain separately audited. Source meaning and timing stay outside generic Communications. Operators continue to use the central schedule and the same current command names.

## Verification and supersession

The architecture verifier rejects ReadModel dependencies on owner Actions, Workflows, the delivery writer and the outbox writer, in addition to direct persistence. The allowed Workflow set includes the new owner; no-model/no-permission-enum rules remain enforced. NotificationDelivery has no transaction exception; the two explicitly reviewed AccountOnboarding exceptions are documented in [ADR-0019](0019-atomic-account-onboarding-owner-composition.md).

Workflow behavior suites cover daily/semantic deduplication, disabled channels, revoked officer/Intelligence authority, cross-Alliance rejection, recipient bounds, current-member selection, retry delivery and command cycling. Projection-only tests remain with ReadModels. Scheduler/provider contracts verify the production command path.

This supersedes the notification-writing placement and the read-model Action clause of ADR-0016. It introduces no new notification fact store or delivery authority.
