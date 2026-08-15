# Contributions domain

## Purpose

Owns explainable non-Event contribution categories/records, calculations, corrections/reversals, data-quality state, unified Player/Alliance/Kingdom contribution-history reporting, exports, and scheduled report definitions.

Events remains authoritative for Event participation, attendance, results, metrics, and historical Event context. Contributions composes those facts into reporting/history and does not duplicate them into a second canonical ledger.

## Owned code

Runtime code in this module owns Contributions persistence/calculation semantics, report schedules/runs, unified history/reporting queries, data-quality workflows, and exports.

## Public contracts

- durable Player-historical non-Event contribution records keyed by `player_id`;
- append-oriented correction/reversal workflow;
- unified Player contribution/Event history across Player, Alliance, and Kingdom Event scopes;
- Alliance historical reporting from Events permanently targeted at the Alliance, including former members;
- Kingdom historical reporting from Events permanently targeted at the Kingdom, including transferred Players;
- compatible metric/category reporting without an unexplained universal score;
- member/manager reporting and opt-in leaderboards; and
- scheduled report definitions consumed by Notifications for due-time coordination.

## Dependencies

- `Events` — authoritative Event participation/result/metric/history facts.
- `Memberships` / `Authorization` — current Alliance authority and eligibility where applicable.
- `Kingdoms` / `Authorization` — durable Player identity and current exact-Kingdom authority.
- `Notifications` — scheduled report-request coordination.
- `Audit` / Platform outbox — privileged/durable evidence.

## Canonical documentation

- [`docs/domains/contributions/`](../../../docs/domains/contributions/README.md)
- [Event contribution and historical intelligence](../../../docs/domains/events/event-contribution-history.md)
