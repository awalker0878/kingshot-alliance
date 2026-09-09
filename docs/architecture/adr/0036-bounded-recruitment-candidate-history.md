# ADR-0036: Bounded Recruitment candidate history

Status: Accepted

## Context

Candidate detail eagerly materialized every note, stage-history record, communication and matching duplicate. Valid historical activity grows these collections indefinitely. The ReadModel controller also delegated page composition to a domain mutation controller and then reloaded the candidate to decorate the response.

## Decision

RecruitmentCandidateDetailQuery owns the authorized read composition. RecruitmentCandidateReadController validates cursor input and adds account/session presentation data. RecruitmentCandidateController retains mutation adapters; its former show implementation and unused read helpers are removed.

Notes, stage history and communications each return an independent PageSlice with at most 25 records and one extra row to determine continuation. Ordering is descending event timestamp and ID. RecruitmentDuplicateFinder returns a 25-record PageSlice ordered by submitted timestamp and ID ascending, preserving the prior oldest-first duplicate ordering.

The existing encrypted ScopedCursorCodec binds each position to Alliance, candidate and history category. Duplicate continuation also includes the normalized email/contact matching facts. A cursor never grants authority: each request reloads current recruiter permission and the current non-anonymized candidate before composing history. Changed duplicate facts invalidate continuation. A removed boundary record does not invalidate the timestamp/ID position.

The page exposes notesPage, historyPage, communicationsPage and duplicatesPage; the old whole-history arrays are removed. Each section can advance or return to its first page while retaining the other sections' cursors and unsaved form state. The existing CursorPagination component accepts explicit state/scroll preservation for these in-page navigations, with other callers retaining their defaults. Summaries are localized in every supported catalogue.

Fresh schema indexes include ID tie-breakers for the three ordered histories and partial normalized email/contact indexes for current duplicate matching. No historical migration, compatibility response or fallback query is introduced.

## Verification and remaining scope

Thirteen PostgreSQL read cases cover hydration/query bounds, deterministic ties across pages, cross-category/candidate/Alliance cursor rejection, tampering, independent sections, new insertions, deleted boundary records, changed duplicate facts, current revoked authority, foreign candidates and the HTTP contract. Desktop/mobile browser cases verify independent navigation, preserved note drafts and responsive rendering.

This decision bounds the four growing history collections. Recruitment configuration catalogues, attached tags/reviewers and roster/template selection projections require their separate coverage under HARD-088; the whole candidate workspace is not declared bounded until those paths are complete. Execution evidence belongs in the delivery ledger under HARD-087.
