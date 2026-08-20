# Capability delivery ledger

Status: Current as of 2026-08-20

This ledger records shipped outcomes, the active capability-completeness program, the remaining evidence gate, and the implementation standard. GitHub remains the source of truth for exact diffs and CI results.

## Merged delivery

| PR                                                              | Slice                   | User outcome                                                                                       |
| --------------------------------------------------------------- | ----------------------- | -------------------------------------------------------------------------------------------------- |
| [#79](https://github.com/awalker0878/kingshot-alliance/pull/79) | Post-Pint stabilization | Restored a green baseline without a PHPStan baseline or compatibility shims.                       |
| [#80](https://github.com/awalker0878/kingshot-alliance/pull/80) | Gift Codes              | Shared, sourced code catalogue with official redemption handoff and per-Governor status.           |
| [#81](https://github.com/awalker0878/kingshot-alliance/pull/81) | Notifications           | In-app, Discord, and Telegram delivery with encrypted endpoints and bounded retries.               |
| [#82](https://github.com/awalker0878/kingshot-alliance/pull/82) | Command overview        | One responsive decision surface for alerts, Events, Gift Codes, and recruitment follow-up.         |
| [#83](https://github.com/awalker0878/kingshot-alliance/pull/83) | Alliance broadcasts     | Scheduled, idempotent announcements to active members' enabled channels.                           |
| [#84](https://github.com/awalker0878/kingshot-alliance/pull/84) | Knowledge provenance    | Searchable versioned guides with source, game-version, locale, and review metadata.                |
| [#85](https://github.com/awalker0878/kingshot-alliance/pull/85) | Player progression      | Freshness-aware, source-labelled observation history and consecutive change detection.             |
| [#86](https://github.com/awalker0878/kingshot-alliance/pull/86) | Recruitment discovery   | Opt-in public discovery, shareable filters, visible attribution, and private conversion analytics. |
| [#87](https://github.com/awalker0878/kingshot-alliance/pull/87) | Bot/API reads           | Revocable least-privilege command, Gift Code, and knowledge reads with bounded responses.          |
| [#88](https://github.com/awalker0878/kingshot-alliance/pull/88) | Mobile/PWA              | Install, update, and offline UX while private application responses remain network-only.           |

Every merged slice passed PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture verification, CodeQL, dependency review, intelligence contracts, visual regression, production image build, ephemeral staging, backup/restore, and image scanning.

## Improvement-program delivery

| PR                                                              | Slice                          | Durable outcome                                                                                       |
| --------------------------------------------------------------- | ------------------------------ | ----------------------------------------------------------------------------------------------------- |
| [#90](https://github.com/awalker0878/kingshot-alliance/pull/90) | Baseline and cleanup           | Established the authoritative inventory, documentation-link gate, and cleanup rule.                   |
| [#91](https://github.com/awalker0878/kingshot-alliance/pull/91) | Architecture enforcement       | Removed V2 visual compatibility structure and made the current visual contract enforceable.           |
| [#92](https://github.com/awalker0878/kingshot-alliance/pull/92) | UX system                      | Standardized accessible busy, validation, outcome, and confirmation behavior.                         |
| [#93](https://github.com/awalker0878/kingshot-alliance/pull/93) | Public webhook contracts       | Replaced dead selectors with emitted Alliance-scoped lifecycle contracts.                             |
| [#94](https://github.com/awalker0878/kingshot-alliance/pull/94) | Webhook delivery recovery      | Added signed test delivery, audited replay, bounded retry, and delivery inspection.                   |
| [#95](https://github.com/awalker0878/kingshot-alliance/pull/95) | Gift Code recovery             | Completed official-provider handoff, terminal outcomes, backoff, and safe retry behavior.             |
| [#96](https://github.com/awalker0878/kingshot-alliance/pull/96) | Operational budgets            | Made reviewed production JavaScript and stylesheet ceilings release gates.                            |
| [#97](https://github.com/awalker0878/kingshot-alliance/pull/97) | Accessibility and localization | Replaced browser prompts with the shared accessible modal contract and an AST-based enforcement gate. |

The temporary phase report was retired at closeout; Git history remains its archive. Current product outcomes live in the [capability catalogue](capability-catalogue.md), user flow in [primary journeys](experience/user-journeys.md), and operational release evidence in the [release checklist](../operations/release/checklist.md).

## Active completeness program

| Order | Slice                         | Exit condition                                                                                                                                   |
| ----- | ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1     | Authoritative baseline        | Fresh-install migrations contain the final pre-deployment schema, retired phase language is removed and every page participates in localization. |
| 2     | Read contracts and pagination | Potentially large lists expose stable opaque cursor traversal with preserved filters and scope tests.                                            |
| 3     | Shared UX and navigation      | Common list, state, receipt and permission-aware navigation patterns are adopted by touched workflows.                                           |
| 4     | Safe bulk actions             | Recruitment, Membership, Event, Contribution and notification bulk actions use preview, per-item authorization/results and failed-item retry.    |
| 5     | Gift Code trust lifecycle     | Review, dispute, expiry, provenance and selective Governor retry are explicit and audited.                                                       |
| 6     | Recurring communications      | Schedule intent and provider delivery outcomes are distinct, inspectable, cancellable and selectively retryable.                                 |
| 7     | Integration contracts         | Outbox writes are standardized, secrets rotate safely and published API/webhook schemas are contract-tested.                                     |
| 8     | External actor parity         | Revocable provider identity links support idempotent authorized self-service writes without duplicated domain rules.                             |
| 9     | Knowledge and operations      | Stale-content review, contextual guidance, safe diagnostics and query/queue budgets are operating.                                               |
| 10    | Release closeout              | Daily workflows have keyboard/mobile/visual coverage and fresh-install, staging and restore checks pass.                                         |

No slice advances on partial UX or documentation. Its owner, permission model, negative and recovery paths, tests, audit/correlation behavior, observability and current-truth documentation ship together.

The safe-bulk program is complete. Recruitment candidate stage triage, Membership status administration, Event cancellation, Contribution approval, and Notification inbox updates now use server-authorized preview, bounded concrete selection, commit-time owner authorization, per-item results, aggregate audit evidence, and failed-item reselection. Owner actions continue to produce their capability-specific history or audit evidence.

The Gift Code trust lifecycle is complete. Normalized duplicates retain append-only source observations, shared `pending`/`valid`/`invalid`/`expired`/`disputed` status is derived from per-Governor evidence, official handoff returns per-Governor receipts, failed-only retry is bounded and ownership-scoped, and hourly maintenance queues idempotent expiry reminders.

The recurring-communications slice is complete. Content owns timezone-safe weekday rules and durable broadcast runs; Communications owns channel fanout and provider outcomes. Managers can send actor-only tests, inspect queued/sent/failed/read counts, cancel future recurrence with the accessible confirmation contract and retry up to 50 still-eligible failures. Editing or archiving content deactivates stale recurrence, while the cross-context management page is composed in an explicit ReadModel.

The integration-contract slice is complete. The allowlisted catalogue now covers Membership, Event, Recruitment, Gift Code and broadcast lifecycles with explicit Alliance/global scope and fail-closed payload validation. Versioned OpenAPI and webhook JSON Schema artifacts are contract-tested against runtime routes/scopes and event requirements. Managers can rotate encrypted signing secrets with one-time display, while provider delivery outcomes remain privacy-safe and retry evidence stays immutable.

The external-actor parity slice is complete. Governors issue ten-minute, one-time Discord/Telegram pairing codes and can inspect or revoke resulting links. Platform stores keyed provider-subject hashes, revalidates link/credential scope for every write, and binds each normalized request to an idempotent receipt. Bot adapters can submit Event responses and registration changes, while the cross-context workflow delegates authorization, response, capacity and waitlist behavior to Operations owner actions.

The knowledge-and-operations slice is complete. Knowledge review state is derived from versioned provenance, due and overdue work appears in the Content workspace, and explicit Event-type links are restored with each revision. Alliance Event pages consume matching published guidance through a read model instead of duplicating strategy text. The Citadel shows bounded privacy-safe failure projections and correlation timelines; outbox retries stop at a configured limit and a password-confirmed operator can release only exhausted failed unpublished work for one audited idempotency-preserving retry cycle.

Closeout requires a clean PostgreSQL installation through every migration, the complete PHP and frontend checks, architecture verification, dependency review, CodeQL, unchanged or deliberately reviewed visual baselines, production-image construction and scanning, ephemeral staging, and a demonstrated backup/restore cycle. Calculators remain outside the release scope until the evidence gate below opens.

## Remaining evidence gate: calculators

The scan found community troop, Governor Gear, Charm, Hero Gear, shard, pet, research, Event, and formation calculators. These are useful discovery evidence, but no inspected source supplied an official or reproducibly versioned Kingshot dataset suitable for product logic.

Calculator work may start only when all of these are true:

1. Every row has a source URI, source label, `observed_at`, game-version boundary, and unit.
2. Values come from one official inspectable table or are reconciled across two independent inspectable sources plus recorded in-game evidence.
3. Disagreements, regional differences, unlock conditions, and unknown values are explicit; unknown never means zero.
4. Each released dataset is immutable, schema-versioned, checksummed, and retained when superseded.
5. Calculation code consumes the dataset through a typed domain contract; Vue components contain no cost tables or formulas.
6. Golden fixtures cover single-step, range, promotion, bonus, rounding, and unavailable-data boundaries.
7. The UI displays dataset version, source, observation date, assumptions, and a report-correction path.
8. Saved scenarios reference their dataset version so later data corrections cannot silently rewrite historical plans.

## Concise implementation plan after the gate opens

1. Land the dataset schema, validator, provenance record, and one reviewed dataset without a user-facing calculator.
2. Implement pure range aggregation and inventory-gap services with golden fixtures.
3. Ship one calculator end-to-end, beginning with the best-supported dataset rather than the largest feature.
4. Add saved scenarios, compare/export UX, keyboard/mobile verification, and visible source/version metadata.
5. Run the complete repository gate set before merge; expand to another calculator only after the first dataset and correction workflow are operating cleanly.

Until the evidence gate opens, calculator pages, guessed formulas, placeholder values, and copied opaque tables are intentionally out of scope.
