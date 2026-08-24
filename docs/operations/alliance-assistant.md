# Alliance Assistant operations

Status: Complete — 2026-08-24

Alliance Assistant is a synchronous, read-only composition surface. The current release uses deterministic intent parsing and owner queries only. It has no external LLM/model-provider dependency, no Assistant database tables, no queue, and no persisted conversation history.

## Configuration

`config/assistant.php` owns bounded runtime controls. Operators may tune limits but must not use configuration to broaden authorization or add an unsourced knowledge fallback.

- `max_question_length` — maximum submitted question length; default 500.
- `event_past_days` — bounded historical Event candidate window; default 0.
- `event_future_days` — bounded future Event candidate window; default 90.
- `content_result_limit` — bounded content search candidate cap where applicable.
- `observation_result_limit` — bounded observation candidate cap.
- `rate_limit_per_minute` — named Assistant request limit; default 30 per authenticated account.

The application service provider registers the `alliance-assistant` limiter. Authenticated account ID is the primary limiter key; IP is only a fallback when no authenticated identity exists.

## Provider posture

There is no external model call in this release. Private questions, roster facts, Alliance guide text, observations, and citation evidence remain inside the application process/database boundary.

Adding a model provider later is a material product/architecture change. It requires an updated product contract and ADR before implementation, an allowlisted provider/data-handling contract, prompt-injection evaluation, explicit data minimization, provider timeout/retry policy, and proof that owner authorization occurs before any content is transmitted.

Provider/model training knowledge must never be an application source of truth.

## Privacy-safe telemetry

Successful reads may log only bounded operational dimensions:

- intent identifier;
- answer status;
- evidence count;
- source-type counts;
- composition latency.

Failures may log:

- allowlisted failure category/class;
- latency;
- normal request/trace correlation supplied by shared observability middleware.

Routine logs must not contain:

- raw question text;
- generated/localized answer text;
- guide excerpts or bodies;
- observation text/details;
- roster notes or Governor names;
- source URLs that contain sensitive query material;
- provider prompts/responses (none exist in this release).

## Failure behavior

Owner authorization failures are returned through the normal application authorization boundary and are not converted into `not_found` after retrieval.

Unexpected owner/composition failures return `503` with the localized `assistant.answers.unavailable` semantic response. The Assistant must not retry against a broader source set and must not fall back to model knowledge.

There is no automatic server-side request retry because the operation is synchronous and read-only. A user may retry a transient unavailable response. Rate-limited callers should wait for the limiter window rather than loop aggressively.

## Recovery

There is no Assistant state to replay or rebuild. Recovery is dependency-oriented:

1. confirm authentication, active Player, and active Alliance context;
2. confirm the relevant owner capability query works directly for the same actor/scope;
3. inspect privacy-safe request/trace logs for failure category and latency;
4. verify database/read dependency health;
5. verify `AllianceAssistantServiceProvider` and `routes/assistant.php` are loaded;
6. verify configured bounds/rate limit are valid integers;
7. restore the failing owner capability first when the error originates there.

Do not create an Assistant cache, shadow database, replicated guide corpus, or elevated service account as a recovery shortcut.

## Security checks

Operational verification should include:

- cross-Alliance denial/non-disclosure tests;
- prompt-injection tests using hostile guide/observation text;
- unsupported-write tests that assert zero mutations;
- log assertions showing question/source text is absent;
- limiter tests;
- response-citation validation;
- architecture tests proving the ReadModel has no persistence/write dependencies.

## Release gate

Alliance Assistant is releasable only when its dedicated behavior/security/frontend/visual tests and the repository's applicable PHP, Pint, PHPStan, frontend lint/format/type/build, Architecture V3, Intelligence, CodeQL, Dependency Review, Visual Regression, production-image/container, staging, clean-install, and backup/restore checks are green on one immutable candidate.
