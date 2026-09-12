# ADR-0035: Current Recruitment lifecycle and protected target authority

Status: Accepted

## Context

Recruitment intake previously depended on HTTP for active Kingdom checks and read optional applicant accounts without lifecycle serialization. Candidate mutations mostly rejected merged records but could repopulate a retained anonymized record. Communication and onboarding updates locked a child before its candidate, reversing retention's parent-before-child order. Reviewer assignment and standalone transfer membership handoff acquired actor membership before a different target under shared Alliance scope. Bulk owner entrypoints relied on HTTP to bound selections.

## Decision

Membership status and Recruitment stage previews normalize duplicate IDs and reject empty or more than fifty distinct selections before scope queries. Execution and aggregate audit receipts use those canonical preview items. Existing per-item authorization and independent commit semantics remain authoritative.

Optional applicant account identity is locked active before Alliance scope; intake then locks the active Kingdom before settings and dependent records. Current account email is checked in the transaction. Anonymous public intake, duplicate-email policy, invitation consumption, answers and delivery effects retain their existing semantics.

RecruitmentCandidate owns an explicit ensureNotAnonymized invariant. Human mutation owners call it after locking the current candidate, including both merge inputs and candidate-bound communication/onboarding actions. The event projection ignores terminal candidates; management detail and duplicate projections exclude them. Historical records remain available to explicit retention/audit queries without a global model scope.

Communication and onboarding owners discover child routing without a lock, lock the scoped candidate shared, validate its lifecycle, and finally lock the child with its candidate binding revalidated. Retention locks candidate exclusively before deleting children and setting the terminal marker. Prepared/sent communication idempotency and current onboarding update behavior are preserved.

Reviewer assignment, standalone transfer membership handoff, membership status/rank administration and specialist role assignment/removal use the established exclusive Alliance scope before acquiring actor and target memberships. They retain current authorization, reviewer eligibility, membership hierarchy, independent Alliance progress and rollback. This contract applies at the owner entrypoint even when a current caller already holds an exclusive scope.

Manual stage changes and bulk stage preview reject Joined independently of HTTP. Only the existing invitation.accepted event projection records Joined, after matching current candidate stage and captured Player. Delayed events do not reopen unsuccessful or anonymized candidates. The projection remains transactional and replay-safe.

Bulk Recruitment execution reports a current authorization failure for each affected candidate while retaining prior independent commits and the aggregate audit receipt. Permission-denied outcomes are localized and only failed IDs are retried after current authority is restored.

## Alternatives and consequences

HTTP-only validation would leave direct callers inconsistent. A global anonymization scope would conceal retained historical records from owners that must inspect them. Locking children first would preserve the retention cycle; acquiring no candidate lock would allow terminal-state races. Replacing the existing Alliance coordination mechanism would introduce a second protocol without a need.

The containing PostgreSQL suite must verify canonical bulk receipts, both account/Kingdom/settings intake orders, terminal mutations and projections, both retention/child orders, changed routing, both opposing officer orders, current membership revocation, independent Alliances and complete late rollback. Execution evidence and remaining scope are recorded under HARD-078–082/084–086 in the delivery ledger.
