# Kingdoms snapshot security review

[← Security documentation](README.md)

**Status:** `KINGDOMS-001` Slice C1 / `K1-P3` validated implementation candidate  
**Scope:** append-only alliance player snapshots, latest/history projection, freshness semantics, manual recording and provenance  
**Dependency:** validated Slice B roster candidate

## Security objective

Slice C1 introduces historical game observations without weakening alliance tenancy, roster authorization, privacy boundaries, or auditability. A shared Kingdom or neutral Player must never become a path to another Alliance's snapshot history.

The protected Slice C1 implementation gate has passed. This review remains candidate acceptance evidence until the slice is accepted into the dependency stack; repository validation does not change the existing real-production approval boundary.

## Assets and trust boundaries

Protected assets include:

- alliance-scoped snapshot history;
- current/latest player power, progression and observed alliance/tag;
- capture time and source/provenance;
- the actor identity associated with a manual observation;
- snapshot audit/outbox evidence; and
- roster freshness derived from historical observations.

Trust boundaries are:

- authenticated application user → active Alliance context;
- member-visible snapshot history → manager-only mutation/provenance details;
- submitted roster-entry ID → Alliance-scoped server-side resolution;
- browser numeric entry → signed-64-bit database value;
- client-supplied capture time → server validation/freshness interpretation; and
- retry/replay → deterministic observation idempotency.

## Threats and controls

### Cross-alliance snapshot disclosure

**Threat:** An authenticated user submits another Alliance's roster-entry ID or relies on a shared Player to read its observations.

**Controls:**

- history and mutation routes require the active Alliance middleware;
- roster entries are re-resolved with `alliance_id = active alliance`;
- snapshot latest/history queries also constrain `alliance_id`;
- Player identity is reference data only and is never an authorization key; and
- feature coverage verifies cross-alliance history reads and mutations fail closed.

**Residual risk:** New future queries must preserve both tenant and roster ownership predicates rather than querying snapshots by Player alone.

### Privilege escalation for observation recording

**Threat:** A normal member records or fabricates historical observations.

**Controls:**

- history reads use `alliance.view`;
- snapshot mutation requires `kingdoms.manage` through the normal permission-union model;
- the POST route additionally requires recent password confirmation; and
- built-in permission defaults remain Owner/Leader/Officer only.

Platform-administrator status does not implicitly bypass alliance RBAC.

### Actor/privacy disclosure

**Threat:** Ordinary members learn management/account metadata from snapshot provenance.

**Controls:**

- members receive game-facing observation fields, capture time and source;
- actor display name is included only in manager responses;
- membership IDs/email and private manager notes are not part of the snapshot member payload; and
- outbox metadata does not include manager notes or account contact data.

Game-facing data is still tenant-scoped even if similar information may be visible in the game itself.

### Replay and duplicate-history inflation

**Threat:** Network retries or repeated submissions multiply the same accepted observation and distort later metrics.

**Controls:**

- the recorder creates a SHA-256 idempotency key from the canonical accepted observation;
- uniqueness is enforced per Alliance in the database;
- exact retries return the existing snapshot; and
- audit/outbox records are emitted only when a new snapshot row is created.

Actor identity is intentionally excluded from observation identity: the same captured observation is still the same observation when submitted twice by different authorized managers.

A different capture timestamp remains distinct so legitimate later repeated measurements are not suppressed.

### Misleading or future-dated observations

**Threat:** A manager records a far-future timestamp to keep a roster record artificially current or corrupt future trend windows.

**Controls:**

- the server parses capture time and rejects timestamps more than five minutes in the future;
- freshness is based on `captured_at`, not row creation time; and
- history visibly preserves capture times and provenance.

**Residual risk:** Manual observations can still be inaccurate or intentionally misleading. Slice C1 does not claim external verification of game data. Later intelligence must communicate data quality rather than treating manual data as independently verified fact.

### Integer overflow and browser precision loss

**Threat:** Oversized or floating-point-converted power values corrupt historical calculations.

**Controls:**

- input accepts decimal digits only;
- canonical power must fit signed 64-bit range (`0` through `9223372036854775807`);
- PostgreSQL stores power as `bigint`; and
- Inertia payloads serialize power as a decimal string so JavaScript does not coerce large values into an unsafe IEEE-754 number.

### Historical tampering

**Threat:** Normal roster edits silently overwrite or destroy observations.

**Controls:**

- Slice C1 exposes create/read snapshot behavior only;
- no snapshot edit/delete HTTP route is introduced;
- roster updates and mark-left operations do not update snapshot rows; and
- tests verify roster edits leave prior observations intact.

Normal tenant lifecycle deletion remains governed by the existing Platform lifecycle/retention contract and is not a roster-history editing path.

### Latest-observation manipulation by insertion order

**Threat:** An older observation entered later becomes the apparent current value simply because it has a newer database creation time.

**Controls:**

- latest selection orders by capture time, not insertion time;
- snapshot ULID is only a deterministic tie-breaker for equal capture times; and
- tests record out-of-order history and verify the newest capture remains current.

### Private information in durable events

**Threat:** Outbox consumers receive manager-only roster notes or unrelated account data.

**Controls:**

- snapshot event metadata contains snapshot ID, roster-entry ID, Player ID, capture time and source;
- private manager notes are excluded; and
- downstream/public Kingdoms API or webhook exposure is not introduced in Slice C1.

## Freshness and abuse boundary

Current/stale/missing is a data-quality indicator, not a member-performance score:

- current: a snapshot within 30 days;
- stale: history exists but the latest eligible capture is older than 30 days;
- missing: no snapshot history.

Slice C1 contains no growth ranking, decline ranking, punitive score, or recommendation. Comparative intelligence remains `K1-P4` and requires its own calculation and abuse review.

## Deferred trust boundaries

The following remain outside Slice C1 and therefore are not trusted input paths yet:

- CSV upload/import (`K1-P5`);
- bots, OCR, scraping or game APIs;
- public snapshot API/webhook exposure;
- cross-alliance/kingdom-wide intelligence; and
- transfer/diplomacy workflows.

Future implementation must add its own authentication, provenance, replay, validation and privacy controls rather than reusing `source` as proof of trust.

## Verification evidence for Slice C1

Protected validation covers:

- migration success on PostgreSQL;
- `kingdoms.manage` plus recent-password enforcement;
- member-readable/manager-write boundaries;
- actor data minimization;
- cross-alliance read and mutation isolation;
- exact retry idempotency and no duplicate audit/outbox records;
- later-capture preservation;
- signed-64-bit power range;
- out-of-order capture/latest correctness;
- current/stale/missing semantics;
- prior history surviving normal roster edits;
- frontend lint/format/type/build checks; and
- inherited staging, backup/restore and image security checks.

Passing repository checks validates the application/repository controls only. Real production infrastructure and production cutover remain governed by the existing production launch approval boundary.
