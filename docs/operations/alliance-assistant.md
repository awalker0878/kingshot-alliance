# Alliance Assistant operations

Status: Kingshot expansion implemented; containing-candidate verification pending — 2026-08-29

Alliance Assistant remains a synchronous, read-only composition surface. It uses deterministic bounded intent parsing and owner queries only. It has no external LLM/model-provider dependency, no Assistant database tables, no queue, no persisted conversation history, and no Assistant mutation worker.

## Configuration

`config/assistant.php` owns bounded runtime controls. Operators may tune limits but must not use configuration to broaden authorization or add an unsourced knowledge fallback.

- `max_question_length` — maximum submitted question length; default 500.
- `event_past_days` — bounded historical Event candidate window; default 0.
- `event_future_days` — bounded future Event candidate window; default 90.
- `content_result_limit` — bounded content search candidate cap where applicable.
- `observation_result_limit` — bounded observation candidate cap.
- `rate_limit_per_minute` — named Assistant request limit; default 30 per authenticated account.

Weekly participation questions use a seven-day past/future authorized Event window and then filter to the current UTC week. The owner batch projection accepts at most 500 already-authorized occurrence IDs and reads only the active Player's participation records.

The Kingshot expansion adds closed operational intents for officer attention, verified Event readiness, Rally gaps, Bear Hunt history, Governor-observation freshness, Transfer verification, Intelligence changes and Territory comparison. These intents reuse exact authorized owner/ReadModel projections; they do not run broad searches or create Assistant-owned facts. A missing owner projection returns `not_found` rather than an evidence-free answer or handoff.

## Provider posture

There is no external model call. Private questions, self roster/participation/assignment state, transfer assessment evidence, Alliance guide text, observations and citations remain inside the application process/database boundary.

Game facts are loaded from the existing immutable `GameWorld/Progression` release. No online scrape or model lookup occurs during an Assistant request.

Adding a model provider later remains a material product/architecture change requiring product contract/ADR review, allowlisted provider/data-handling rules, prompt-injection evaluation, data minimization, timeout/retry policy and proof that owner authorization occurs before any content transmission.

## Frontend localization and performance posture

The GameWorld extension adds native Assistant strings for every supported locale and five new bounded discovery prompts while preserving the original four. TypeScript locale-map contracts and architecture tests enforce that complete extension shapes exist.

Assistant-only extension catalogues load dynamically with the `assistant` localization domain. They must not be moved into the shared application entry or made eager for unrelated pages. Release verification must keep the existing initial-JavaScript, largest-page and stylesheet budgets green; increasing a budget is not an acceptable fix for an Assistant-only regression.

Transfer requirement presentation localizes the canonical owner requirement key and requirement state. English owner `explanation`/`nextAction` prose is not treated as translated interface copy.

## Privacy-safe telemetry

Successful reads may log only bounded operational dimensions:

- intent identifier;
- answer status;
- evidence count;
- source-type counts;
- composition latency.

Failures may log allowlisted failure category/class, latency and shared request/trace correlation.

Routine logs must not contain raw question text, localized answer text, GameWorld value payloads/source excerpts, guide bodies, observation/transfer-observation details, roster/assignment notes, Governor names, or sensitive source URLs.

Metrics may count `game_fact` unknown/conflicting outcomes and action-handoff offers, but labels must remain bounded and must not include entity/player/question text.

## Dependency-specific failure behavior

### Progression

If the immutable release cannot be loaded or parsed, return `unavailable`; do not fall back to model knowledge. A release-level `unknown` or `conflicting` fact is a successful sourced answer state, not an infrastructure failure.

### Participation and BattlePlans

Dependent self state is read only after authorized Event resolution. An ambiguous Event stops the flow before self-state retrieval. Batch participation queries remain scoped to the active Player.

### Kingdom Transfers

No transfer result is returned unless the active Player is a legitimate, visible, non-withdrawn participant in the current plan. A supplied Kingdom number never expands scope. Troubleshooting uses the canonical transfer planning/readiness capability rather than an Assistant override.

### Territory Planning

The Assistant reads only immutable published revisions attached to the Event occurrence. Missing or ambiguous attachments are repaired in the normal Event/TerritoryPlanning workflow; do not point the Assistant at mutable plan head state or the management revision catalogue.

### Handoff

A handoff is a normal GET link only. There is no handoff retry, worker, or Action. The destination remains authoritative for authorization, stale authority-context handling, validation and mutation. Event, Roster, Rally, Transfer, Evidence and Territory write-like requests receive a handoff only when the Assistant has first obtained an authorized source item to cite.

### Officer briefs

The Command Overview composes Daily Officer, Upcoming Event and Post-Event Closeout briefs from current owner facts. A semantic fingerprint excludes generation time so the same meaning produces the same delivery identity. Before queueing `officer.brief`, the publisher re-resolves the recipient Player/account and current R4/R5 `MembershipManage` authority. Communications then owns channel preferences, idempotency, attempts, retries and receipts; queued is never reported as delivered.

The scheduler runs bounded recipient sweeps every 15 minutes. Daily briefs become eligible after 09:00 in each account's configured timezone and queue once per recipient/channel/local date. Upcoming Event and Post-Event Closeout briefs queue only when the group is available and its semantic fingerprint changes. Scheduled `--cycle` runs keep only a short shared-cache membership cursor so later pages cannot starve and wrap after the final page; operators can also page a truncated manual sweep with its returned cursor. There is no Officer Brief table, scheduler-specific fact store or generic task store.

Recovery starts by rebuilding the authorized brief, checking the current semantic fingerprint and inspecting Communications delivery state. External failures use the generic `notifications:deliver` retry path; a queued row is a receipt of accepted delivery work, not proof of provider delivery. Sweep logs contain counts, duration and truncation state only.

## Recovery

There is no Assistant state to replay or rebuild. Recovery is dependency-oriented:

1. confirm authentication, active Player and active Alliance context;
2. confirm the relevant owner query works directly for the same actor/scope;
3. for Event-dependent questions, confirm the Event resolves uniquely through authorized calendar visibility;
4. for Game facts, confirm the current Progression release/checksum and owner fact query;
5. for transfers, confirm current participant scope and canonical readiness evaluation;
6. for territory plans, confirm a published revision is attached to the occurrence;
7. inspect privacy-safe trace/failure dimensions;
8. verify database/read dependency health;
9. restore the failing owner capability first.

Do not create an Assistant cache, shadow database, replicated game-fact corpus, management-query bypass or elevated service account as a recovery shortcut.

## Security checks

Operational verification includes:

- cross-Alliance Event non-disclosure;
- self-only participation/assignment/transfer tests;
- unknown/conflicting Progression behavior;
- source-release/checksum citation assertions;
- prompt-injection tests using hostile guide/observation text;
- recognized-write handoff tests asserting zero mutations;
- unknown-write unsupported tests;
- log assertions showing question/source text is absent;
- limiter tests;
- response-citation validation;
- architecture tests proving the ReadModel has no persistence/write Action dependency and no broad management projection dependency;
- exact nine-prompt baseline discovery coverage plus typed Kingshot expansion prompts;
- typed native extension locale-map coverage;
- dynamic Assistant-domain localization imports and repository performance budgets.

## Release gate

The GameWorld extension is releasable only when its product delivery ledger is complete and the same immutable candidate is green across applicable PHP tests, Pint, PHPStan, frontend lint/format/type/build/performance budgets, Architecture V3, Intelligence Verification, CodeQL, Dependency Review, Visual Regression, production-image/container, staging, clean-install and backup/restore checks.
