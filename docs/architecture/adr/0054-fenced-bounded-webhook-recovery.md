# ADR-0054: Fenced and bounded webhook recovery

Status: Accepted

## Problem

An early delayed job could claim a Pending delivery before `available_at`. Queue exhaustion could update a delivery without proving it belonged to the same provider attempt. Stale recovery materialized every eligible row, and concurrent scheduler invocations could enqueue the same Pending rows before a worker claimed them.

## Decision

Each provider attempt receives an immutable UUID token under the delivery lock. Completion and the queue failure callback may finalize only a row carrying that exact token. Attempt counts remain monotonic. Manual retry and stale-lease recovery clear the old token; they never reset attempt counts.

The scheduler bounds stale recovery to its clamped work limit. It reserves due Pending rows as `queued` under `FOR UPDATE SKIP LOCKED`, then dispatches only those IDs after the transaction. A job may claim Pending or queued work only when `available_at` is due. Queued and delivering leases older than five minutes return to Pending in bounded batches.

## Consequences

Concurrent sweeps have disjoint fan-out and bounded memory. A stale response or failed callback cannot finalize a newer attempt. A worker interruption before handling is recoverable without an unbounded scan. Network delivery remains at-least-once; fencing protects internal truth but cannot recall a request already accepted by a remote system.

## Verification

Owner tests cover future-due rejection, exact attempt fencing, bounded stale recovery, queued reservation and repeated sweeps. PostgreSQL concurrency, queue behavior, fresh-schema indexes and the normal containing gates remain required by HARD-110 before completion.
