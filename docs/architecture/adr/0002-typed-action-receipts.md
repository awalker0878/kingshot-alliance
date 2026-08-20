# ADR-0002: Typed action receipts

Status: Accepted

Date: 2026-08-20

## Context

Mutation controllers returned unrelated `status` strings, while individual Vue pages decoded those strings into messages and rendered their own success banners. Some pages rendered raw internal codes, some replaced every outcome with one generic sentence, and others encoded parameters into colon-delimited strings. Adding a mutation therefore required coordinating an undocumented transport convention with page-local logic, and successful behavior differed between otherwise similar workflows.

## Decision

Successful application mutations return an `ActionReceipt` through the `actionReceipt` flash key. The receipt is a stable transport value with:

- a kebab-case `code`;
- string or numeric localization `parameters`;
- a supported `tone` of `success`, `warning`, or `info`.

The base HTTP controller creates success receipts. Inertia middleware exposes the receipt once, and `AppLayout` owns its accessible, polite-live-region presentation. The English core catalogue owns `receipts.<code>` messages. The receipt coverage gate fails when a controller code and the catalogue differ.

Framework authentication protocols may retain their framework-defined `status` values when rendered outside `AppLayout`, such as password reset and email-verification responses. Domain pages do not implement a parallel status path.

Receipts confirm that a command was accepted or completed; they do not replace durable audit events, delivery history, per-item bulk results, or recoverable failure details.

## Consequences

- Mutation feedback appears in one consistent location with one accessibility contract.
- Controllers send stable data rather than localized presentation text.
- Parameters remain structured and can be localized without parsing string conventions.
- New receipt codes require an English catalogue entry before frontend validation passes.
- Pages retain local error notices only for client-side or action-specific failures that are not redirect receipts.

## Rejected alternatives

- Page-local status switches were rejected because they duplicate transport and presentation behavior.
- Flashing translated server strings was rejected because it couples domain adapters to the active UI language and cannot be contract-tested cleanly.
- Keeping both `status` and `actionReceipt` for domain mutations was rejected because the application is not deployed and has no compatibility requirement.
