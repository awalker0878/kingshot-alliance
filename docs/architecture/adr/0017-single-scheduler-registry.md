# ADR-0017: One scheduler registry with owner command adapters

Status: Accepted

Date: 2026-09-08

## Previous architecture and problem

Application schedules were split between `routes/console.php`, bootstrap callbacks and `GiftCodesServiceProvider`. Event reminders, immediate notification delivery, Gift Code reconciliation and source backfill were each registered twice through different adapters. Their separate event identities and mutexes allowed both registrations to run despite overlap protection. Callback-only tasks were also absent from the operator command catalogue.

ADR-0016 already identifies console routes as the central schedule location, but its tests did not enforce this against the booted scheduler.

## Alternatives

Keeping the split registration would preserve duplicate work and require operators to inspect three sites. Moving every schedule into individual providers would distribute global coordination again. A generic schedule framework would add indirection without solving a domain problem.

## Decision and ownership

Use `routes/console.php` as the single global scheduler registry. Every recurring application workload invokes a thin Artisan command registered by its owning package. Bootstrap configures the application; providers bind services and register commands; neither adds a parallel schedule.

Keep source Actions and their bounded/cursor semantics in their existing capabilities. Add owner commands for King Perk reminders, personal/workspace Gift Code notifications, source alerts, contributor/acquisition projections, Kingdom delegation expiry and evidence retention. Preserve unique workload cadence and batch sizes. Remove duplicate bootstrap registrations instead of adding aliases or fallback callbacks. Immediate delivery has the existing `notifications:deliver` entry point.

All registered tasks, including queue pruning, use single-server coordination and overlap protection. Distinct Officer Brief daily/event groups are intentionally separate workloads with separate command parameters/mutexes.

## Scaling, security and operational implications

One registry prevents accidental duplicate scheduler invocation but is not an exactly-once execution guarantee. Owner Actions must still enforce idempotency, current authorization and bounded work under manual invocation, retry and worker failure. This change does not transfer business authorization to the scheduler.

Operators can discover every task through `artisan list` and `schedule:list`, invoke the same bounded command manually, and inspect structured results or counts. Reconciliation/backfill command failure exit codes remain authoritative. Redis remains the hosted shared coordination dependency. Broader workload starvation/concurrency review remains tracked in the hardening program.

## Verification and supersession

`SchedulerOwnershipV3Test` boots the real console kernel and checks registered command existence, unique workloads/mutexes, coordination, and required cadence/bounds. It also prohibits scheduler registration in bootstrap/providers. `ConsoleCommandOwnershipV3Test` enforces owner provider registration.

This supersedes bootstrap/provider schedule placement, including the acquisition-intelligence provider schedule. It reinforces ADR-0016; it does not change the semantics of owner Actions.

Framework reference: [Laravel 13 scheduling](https://laravel.com/framework/docs/13.x/scheduling) documents console-route scheduling, command adapters and shared-cache single-server coordination. The one-registry restriction is this application's architectural decision.
