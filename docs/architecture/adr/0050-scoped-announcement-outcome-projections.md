# ADR-0050: Complete scoped announcement outcome projections

Status: Accepted

## Context and problem

Content owns announcement intent and recipient-preparation progress; Communications owns logical messages, their read state and concrete delivery outcomes. The management read model previously sampled at most 1,000 messages and 5,000 deliveries, then presented derived read/status counts as totals. It correlated run metadata without requiring the actual Content subject and Alliance metadata to agree. Above those limits, the display undercounted facts and could attribute malformed cross-Alliance metadata to the wrong run.

[ADR-0049](0049-bounded-announcement-occurrences.md) remains authoritative for occurrence preparation, fairness and resumable recipient work. Preparation completion is not delivery success or a read receipt. Improving this presentation must not create a second outcome authority or weaken retry authorization.

## Decision and ownership

Communications provides `AnnouncementDeliverySummaryQuery` and an immutable `AnnouncementDeliverySummary` value object. The consuming manager path must first authorize the active actor through ContentManage and supply the actual Alliance and a bounded map of run IDs to their Content subjects. The internal projection does not grant permissions from arbitrary client identifiers. It has no dependency on Content models or the read model; it only reads its own retained records.

Every included logical message must match the announcement notification type, Content subject type, requested Alliance metadata, exact run ID, actual subject ID and matching Content metadata. Inconsistent or missing metadata is not an alias or fallback to another source identity. There is no legacy acceptance mode for the fresh application.

For at most 100 selected runs, one SQL aggregate returns complete retained logical-read and route-status counts. Reads count distinct logical messages rather than joined routes, including read messages without any route. Every current delivery status is represented; an unknown status is an explicit failure, not silently lost data. No audience or message body is materialized into application memory.

A second query independently returns at most 50 retry candidates per run, ranked by descending creation time and ID. A complete candidate count remains distinct from that bounded ID selection. The interface displays selected versus total candidates whenever they differ; it must not label a partial selection as all failures. Candidates are failed routes below their attempt limit. They are observations, not a promise that retry remains authorized: the existing retry Actions revalidate source scope, current state and authority when invoked.

`AnnouncementBroadcastManagementQuery` composes these owner results with Content's existing preparation counters. It no longer imports Communications models or interprets delivery statuses. No persisted counter, cache, duplicate query implementation, compatibility DTO or general-purpose analytics framework is introduced.

## Query and schema boundaries

Query count is two for a nonempty scope, independent of the selected run count. Returned aggregates are bounded by the run limit and retry IDs by 50 per run. The database still scans/aggregates matching retained rows and ranks failed candidates; bounded response size does not imply constant database work at arbitrary volume.

The canonical fresh notification-table migration defines `notification_message_broadcast_scope` on notification/subject type, Alliance and run metadata expressions, and subject ID. This supports the actual source correlation rather than a broad table scan by convention. There is no separate upgrade or backfill migration. Query plans and retention growth remain operational concerns.

## Alternatives and trade-offs

Raising sample limits merely moves the correctness defect. Loading all messages/routes gives exact counts at unbounded memory cost. A read-model-owned persisted counter would require another write/recovery authority and synchronization protocol without demonstrated need. A cross-context query joining internal Content tables would duplicate ownership checks and couple Communications to another context's schema. Exact owner-local aggregates for explicitly authorized source pairs keep the facts in one place while bounding the returned data.

The two statements use ordinary database observation semantics; they do not claim a synchronized snapshot while deliveries are changing. A candidate can disappear or change between display and retry, which the authoritative mutation already handles. Counts describe retained messages/routes, not all-time records after retention removes them, external provider activity not recorded locally, or proof that a recipient read something outside the application.

## Verification and remaining scope

Real PostgreSQL regression fixtures exceed the old limits: 1,205 logical messages and 6,025 concrete routes. Coverage includes all statuses, no-route reads, complete retry counts with deterministic 50-ID selections, two independent run selections, fixed query count, invalid/oversized/empty scopes, unknown statuses, the fresh index, inconsistent source metadata, actual manager HTTP reads and revocation of rank or membership. An architecture contract rejects direct Communications-model interpretation in this read model; a Node source contract checks the selected/total label and all 17 locales.

The three old-projection failures were reproduced before replacement. Executed results and containing verification belong to HARD-106 in the delivery ledger. Acceptance of this ownership decision does not mark the entire item Complete. Unbounded Content catalogue, schedule, revision and media loading, plus older run-history continuation, remain separate unfinished portions of HARD-106. No arbitrary truncation of those surfaces is approved by this decision. Source/recipient work from HARD-104 and the existing source/attempt/member/endpoint-generation fences remain unchanged.
