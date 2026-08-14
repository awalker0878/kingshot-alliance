# Notifications domain

## Purpose

Owns durable Event reminder rule/delivery coordination and scheduled Contribution-report due-time coordination.

## Owned code

Runtime code owns Event reminder rules, Player-specific reminder deliveries, audience resolution, due scheduler actions, outbox-completion handling, and Contribution-report due-time coordination.

## Public contracts

- deterministic Event reminder identity by rule + occurrence + Player;
- exact Player/Alliance/Kingdom audience resolution from current source facts;
- durable reminder states and idempotent scheduler recovery; and
- deterministic scheduled Contribution-report request coordination.

## Dependencies

- `Events` — authoritative occurrences and participation facts.
- `Kingdoms` — durable Player ownership/current context.
- `Contributions` — report schedules and report facts.
- `Platform` — transactional outbox and scheduler infrastructure.

## Canonical documentation

- [`docs/domains/notifications/`](../../../docs/domains/notifications/README.md)
- [Event reminders](../../../docs/domains/notifications/event-reminders.md)
