# Contributions report exports

[← Contributions interfaces](README.md)

**Document type:** Living focused interface contract  
**Status:** Current  
**Owning domain:** Contributions  
**Capability:** Privileged contribution report exports  
**Code owner:** `app/Domain/Contributions`

## 1. Contract scope and owner

Contributions owns the first-party manager export contract for Alliance contribution reporting. The export composes Contributions-owned non-Event records with Events-owned historical result/metric facts for Events permanently targeted at the Alliance. Events remains authoritative for Event facts; export generation does not materialize a second Event ledger.

The implementation provides CSV and SpreadsheetML XML from one canonical report row projection while recording an immutable report-run evidence record for every explicit export request.

This contract is distinct from Integrations' `/api/v1/contributions` JSON projection and from Notifications scheduled-delivery coordination.

## 2. Entry points and caller classes

The first-party entry points are:

- `GET /alliance/contributions/export.csv` → CSV;
- `GET /alliance/contributions/export.xls` → SpreadsheetML XML served with an `.xls` filename.

Both routes are declared in `routes/contributions.php` and handled by `ContributionController`, which delegates content generation/evidence creation to `ContributionReportExporter`.

## 3. Authorization, tenancy and rate limits

Both export routes require authenticated/verified first-party Identity, the exact active Player, the active Alliance context, recent password confirmation through the privileged Contributions route group, and current `contributions.manage` authority for that Alliance.

Platform Administrator status is not a game-domain bypass. Historical Event rows may include Players who have since left because row ownership follows immutable Event `alliance_id`, while export authorization is evaluated from current Player authority.

Each route is throttled at 10 requests/minute. The request does not accept a caller-selected Alliance identifier.

## 4. Request and input format

The current export routes take no report-format body and no filter/query schema. Route selection determines `csv` versus `spreadsheet`.

`ContributionReportExporter` accepts only the internal format values `csv` and `spreadsheet`; unsupported formats fail rather than falling back silently.

The exported row set comes from `AllianceContributionReportQuery`, which composes current canonical Contributions records and Events-owned historical rows. Caller input cannot redefine historical Alliance ownership or Event metric compatibility.

## 5. Response and output format

The current report version is exactly:

```text
event-history.v2
```

Every row uses this ordered column contract:

```text
report_version
alliance_id
record_kind
record_id
event_id
occurrence_id
event_scope
event_type
event_started_at
historical_alliance_id
historical_alliance_name
historical_kingdom_id
player_id
player
event_outcome
event_rank
event_score
metric_key
metric_label
metric_dimension
metric_unit
metric_value
category
unit
value
period_start
period_end
status
source
data_class
evidence
calculation_key
calculation_version
correction_of_record_id
recorded_at
approved_at
reversed_at
reversal_reason
correction_reason
```

`record_kind` distinguishes Events-owned rows from Contributions-owned rows. Event columns are populated only where meaningful; Contributions category/value/lifecycle fields remain available for non-Event records. Historical Alliance/Kingdom values describe the record at the time of the Event and are not authorization state.

### CSV

- MIME: `text/csv; charset=UTF-8`
- filename: `<alliance-slug>-contributions.csv`
- first row is the exact ordered header set above;
- values use standard CSV quoting/escaping.

### Spreadsheet export

- bytes are **SpreadsheetML XML**, not OOXML `.xlsx`;
- MIME: `application/vnd.ms-excel; charset=UTF-8`;
- filename: `<alliance-slug>-contributions.xls`;
- worksheet name: `Contributions`;
- cells are serialized as string-valued SpreadsheetML cells.

The controller response exposes the report version/checksum from the completed report run according to the first-party download contract.

## 6. State changes, events and asynchronous behavior

Export generation executes in a repeatable-read database transaction on PostgreSQL. Current Alliance mutation authority is re-established inside that transaction and the report projection is generated from one stable snapshot.

Every successful explicit export creates a `ContributionReportRun` containing:

- Alliance identity;
- requesting `player_id`;
- format;
- `status = completed`;
- `report_version = event-history.v2`;
- current filters (`[]` in this contract);
- row count;
- SHA-256 content checksum;
- a unique export-run evidence/idempotency key; and
- completion timestamp.

The export is audited as `contribution.report.exported` with Player actor, format, version, row count, and checksum. HTTP file generation is synchronous; Notifications is not used for the explicit download itself.

## 7. Failure, idempotency and retry

Authentication, Player-context, password-confirmation, permission, and rate-limit failures occur before privileged disclosure. Unsupported formats fail closed. A generation/read failure does not produce a partially successful report run.

An explicit HTTP export request is not one idempotent business command: each successful request creates a new report-run evidence record. Two runs over unchanged data may produce identical bytes/checksum while retaining distinct run identities.

Historical Event composition is read-only with respect to Events, so retries cannot duplicate Event result/metric facts.

## 8. Versioning and compatibility

`ContributionReportExporter::REPORT_VERSION` is the explicit schema-semantics version and is currently `event-history.v2`.

Within `event-history.v2`, consumers may rely on the documented ordered columns, format distinction, Event/non-Event row composition, version/checksum behavior, and SpreadsheetML-not-OOXML representation. Removing/renaming/reordering columns, changing historical identity semantics, changing spreadsheet bytes, or changing version/checksum behavior requires an explicit version/compatibility review.

The external Integrations API is independently versioned under `/api/v1`; it is not a compatibility alias for this export format.

## 9. Security, privacy and operational constraints

Exports are privileged Alliance-private disclosure surfaces and may include historical results for former members. Current authorization must therefore be established before the report is generated, but current membership must not filter historical Event rows after authorization succeeds.

Evidence, Player identity, historical affiliation, calculations, corrections, reversals, and Event metrics may contain operationally sensitive information. Responses are private downloads; audit/report-run evidence retains checksum/provenance rather than copying the full file payload.

Operational diagnosis/recovery is covered by [Contributions operations](../operations/README.md). API disclosure security remains separately owned by Integrations.

## 10. Tests, non-capabilities and related documentation

Tests protect current Player authorization, former-member historical inclusion, exact format/version/header ordering, Event/non-Event row provenance, report-run persistence/checksum, and the distinction between CSV, SpreadsheetML, and external API JSON.

This contract does **not** provide:

- anonymous/public report export;
- `.xlsx` OOXML output;
- an Event-to-Contributions materialization/reconciliation ledger;
- a contribution import format;
- a stable external machine API schema; or
- asynchronous scheduled email delivery semantics for an explicit download.

Related documentation:

- [Contributions interface profile](README.md)
- [Contributions domain](../README.md)
- [Event history composition](../event-history-composition.md)
- [Event contribution and historical intelligence](../../events/event-contribution-history.md)
- [Contributions operations](../operations/README.md)
- [Integrations API](../../integrations/api.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
