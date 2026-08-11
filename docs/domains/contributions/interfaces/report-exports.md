# Contributions report exports

[← Contributions interfaces](README.md)

**Document type:** Living focused interface contract  
**Status:** Current  
**Owning domain:** Contributions  
**Capability:** Privileged contribution report exports  
**Code owner:** `app/Domain/Contributions`

## 1. Contract scope and owner

Contributions owns the first-party manager export contract for Alliance contribution reporting. The implemented contract provides CSV and SpreadsheetML XML outputs from the same approved reporting rows while recording an immutable report-run evidence record for every explicit export request.

This contract is distinct from Integrations' `/api/v1/contributions` JSON projection and from Notifications scheduled-delivery coordination.

## 2. Entry points and caller classes

The first-party entry points are:

- `GET /alliance/contributions/export.csv` → CSV;
- `GET /alliance/contributions/export.xls` → SpreadsheetML XML served with an `.xls` filename.

Both routes are declared in `routes/contributions.php` and handled by `ContributionController`, which delegates content generation/evidence creation to `ContributionReportExporter`.

## 3. Authorization, tenancy and rate limits

Both export routes require:

- authenticated session;
- verified Identity;
- active `alliance.context`;
- recent password confirmation through the privileged Contributions route group; and
- `contributions.manage` authorization.

Each route is throttled at 10 requests/minute. The tenant is the active Alliance; the request does not accept a caller-selected Alliance identifier.

## 4. Request and input format

The current export routes take no report-format body and no filter/query schema. Route selection determines `csv` versus `spreadsheet`.

`ContributionReportExporter` accepts only the internal format values `csv` and `spreadsheet`; unsupported formats fail rather than falling back silently.

The exported row set comes from the canonical Contributions reporting query. Caller-supplied evidence, record corrections, calculation provenance, and current status are represented only according to that query's accepted rows.

## 5. Response and output format

The current report version is exactly:

```text
phase5.v1
```

Every row uses this ordered column contract:

```text
report_version
alliance_id
record_id
member
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

### CSV

- MIME: `text/csv; charset=UTF-8`
- filename: `<alliance-slug>-contributions.csv`
- first row is the exact ordered header set above;
- values are emitted using standard CSV quoting/escaping.

### Spreadsheet export

- bytes are **SpreadsheetML XML**, not OOXML `.xlsx`;
- MIME: `application/vnd.ms-excel; charset=UTF-8`;
- filename: `<alliance-slug>-contributions.xls`;
- worksheet name: `Contributions`;
- cells are serialized as string-valued SpreadsheetML cells.

Both responses include:

- `X-Report-Version: <report_version>`; and
- `X-Report-Checksum: <sha256 of exact response content>`.

## 6. State changes, events and asynchronous behavior

An export is a synchronous read/generation operation **plus** evidence persistence. Every successful explicit export creates a `ContributionReportRun` containing:

- Alliance and requesting User identity;
- format;
- `status = completed`;
- `report_version = phase5.v1`;
- current filters (`[]` in this contract);
- row count;
- SHA-256 content checksum;
- a unique export-run idempotency/evidence key; and
- completion timestamp.

The export is audited as `contribution.report.exported` with format, version, row count, and checksum. The HTTP response is not queued and does not rely on Notifications/outbox delivery.

## 7. Failure, idempotency and retry

Authentication, tenant, password-confirmation, permission, and rate-limit failures fail before privileged disclosure. Content-generation allocation/read failures are server failures rather than partially successful downloads.

An explicit HTTP export request is not defined as an idempotent business command: each successful request creates a new report-run evidence record. Two runs over unchanged data may produce identical content and therefore the same SHA-256 checksum while retaining distinct run identities.

Retrying after an unknown client/network outcome may therefore create another valid run; consumers should use the response checksum/version and retained report-run evidence rather than assume one run per human intent.

## 8. Versioning and compatibility

`ContributionReportExporter::REPORT_VERSION` is the explicit schema-semantics version and is currently `phase5.v1`.

Within `phase5.v1`, consumers may rely on the documented ordered columns, format distinction, version/checksum response headers, and SpreadsheetML-not-OOXML representation. A change that removes/renames/reorders columns, changes semantic interpretation, changes the spreadsheet byte format, or alters checksum/version behavior requires version/compatibility review plus documentation/tests.

The external Integrations API is independently versioned under `/api/v1`; it is not a compatibility alias for `phase5.v1` exports.

## 9. Security, privacy and operational constraints

Exports are privileged tenant-private disclosure surfaces. The `evidence`, member, calculation, correction, and reversal fields may contain operationally sensitive Alliance information and must not be exposed anonymously or through a lower-privilege member path.

Responses should be handled as private downloads. Export evidence retains checksum/provenance rather than copying the entire file into audit metadata.

Operational diagnosis/recovery of reporting persistence is covered by [Contributions operations](../operations/README.md). API disclosure security is separately owned by Integrations.

## 10. Tests, non-capabilities and related documentation

Tests should protect manager authorization/tenant isolation, exact format/version/headers, row provenance, report-run persistence/checksum, and the distinction between CSV, SpreadsheetML, and external API JSON.

This contract does **not** provide:

- anonymous/public report export;
- `.xlsx` OOXML output;
- a contribution import format;
- a stable external machine API schema; or
- asynchronous scheduled email delivery semantics.

Related documentation:

- [Contributions interface profile](README.md)
- [Contributions domain](../README.md)
- [Event reconciliation](../event-reconciliation.md)
- [Contributions operations](../operations/README.md)
- [Integrations API](../../integrations/api.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
