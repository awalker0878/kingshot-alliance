# ADR-0057: Bounded Alliance export preparation

Status: Accepted

## Context

The synchronous Alliance export collected every scoped row into PHP arrays before encoding JSON and checking the 100 MiB limit. A large backlog or field could exhaust memory before the existing safety check ran.

## Decision

DataGovernance retains the export owner and discovers the same public tables with an Alliance scope. It starts its own PostgreSQL repeatable-read transaction, authorizes the current Platform grant and locks the current Alliance through existing owner contracts. Nested caller transactions are rejected because they cannot provide that isolation boundary.

Each table first computes its redacted JSON byte count in PostgreSQL. The owner rejects an oversized table before transferring row payloads to PHP. A transaction-scoped, forward-only cursor then transfers at most 25 chunks of 16,384 characters per fetch. Primary-key order and chunk order produce complete deterministic row ordering. The buffer checks the actual byte budget on every write, including metadata and separators. It uses an automatically removed temporary file, never a whole-export PHP array or string.

Discovered identifiers are quoted by the database grammar. A dedicated export column expression emits only a quoted identifier, a fixed text cast or the fixed redaction marker. API and pairing hashes, webhook signing secrets and multi-factor secrets keep their existing redaction. JSON, decimal, timestamps and other textual PostgreSQL values retain the previous PDO export string representation; booleans, integers and floating-point values retain native scalar representation. JSON is compact, with the same v3.1 semantic structure.

Only a completely prepared export receives success metadata and audit evidence. SHA-256 is computed over the prepared stream. The HTTP adapter streams that file after the transaction commits and keeps the checksum and row-count headers. Failed preparation rolls back success evidence and releases the temporary file and transaction-scoped cursors.

## Consequences and verification

The database must read the selected data to count and serialize it; this is a complete export, not a paginated preview. PHP transfer memory is bounded by the chunk batch and temporary file buffering, and the prepared file is bounded at 100 MiB. PostgreSQL may use its configured sort storage while ordering a table. No export data is persisted as an application asset.

Regressions cover 61 scoped rows, a Unicode field spanning chunks, JSON text and scalar fidelity, secret redaction, row counts, the checksum and HTTP stream, current grant revocation, an oversized field rejected before row transfer, and late metadata failure followed by retry. Execution evidence is tracked under HARD-113 in the delivery ledger.

PostgreSQL defines the transaction and incremental-fetch behavior in its [DECLARE documentation](https://www.postgresql.org/docs/18/sql-declare.html).
