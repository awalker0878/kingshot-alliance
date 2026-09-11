# ADR-0051: Bounded Transfer verification overviews with explicit coverage

Status: Accepted

## Problem

The dashboard and all consuming Assistant/Officer Brief paths loaded every current-plan participant and evaluated every participant against the canonical evidence rules synchronously. HARD-093 bounded evidence for one participant, but work still grew with the entire plan. Simply taking the first rows and retaining the old “verified” state would convert unassessed participants into a false clearance. Copying eligibility conditions into aggregate SQL would create a competing rule implementation.

## Decision and ownership

GameWorld/KingdomTransfers owns `TransferVerificationPreviewQuery` and the immutable scalar `TransferVerificationPreview`. It reauthorizes the current actor and Alliance, selects the current plan, and evaluates at most the first 25 active participants in stable ID order through the existing `TransferEligibilityQuery`. No evaluator, evidence selector or outcome rule is copied. Self assessment and the paginated readiness workspace continue to use the same canonical evaluation composition.

The participant statement returns at most 25 models and includes whole-active-plan SQL window aggregates for total participants and manually blocked readiness state. The totals and selected rows therefore share one statement snapshot; the limit does not truncate the totals. No unrelated relationship graph is loaded. The owner uses the existing attention condition: manual blocked readiness or a canonical Blocked/NeedsVerification outcome. Whole-plan manual blockers plus assessed non-manual attention findings form the known affected count without double counting. That count is a lower bound when canonical assessment is incomplete, not an estimate of the unassessed population.

Coverage exposes total, assessed, unassessed, complete, exact manual-blocked count and whether returned affected IDs are complete. The affected ID list is bounded by the assessed preview. It is not a complete selection when either assessment or IDs are incomplete. Unknown missing assessment output fails explicitly; it cannot be treated as a clear outcome. A missing or foreign window does not permit assessment using another Alliance's facts.

`AllianceCommandQuery` consumes this owner projection rather than constructing eligibility itself. A partial preview is `assessment_incomplete` and actionable even with zero known findings; it can never emit `verified`. Complete small plans preserve the existing verified/needs-attention distinction, and missing-window behavior remains explicit. The dashboard labels the known count as “at least” and reports the unassessed count, linking to the paginated current readiness workspace. This is not a statistical sample and no extrapolation is performed.

The Assistant's answer, evidence and citations preserve coverage, not merely the count. Officer Brief facts/fingerprints carry coverage; a delivered daily brief explicitly mentions unassessed Transfer participants and retains bounded scalar coverage metadata. A projection consumer must not remove that uncertainty and claim complete assessment. There is no persisted status, cache authority, new notification type or source-authorization exception.

Remove the eager `TransferParticipantQuery::forPlan` after its final live caller is migrated. Do not retain a compatibility entry point. The distinct `TransferEligibilityQuery::forPlan` remains the canonical evaluator over a supplied bounded collection; it is not the removed eager query.

## Alternatives and trade-offs

Chunking the entire plan would bound PHP memory but retain unbounded synchronous evaluation and repeated cross-capability work. Higher sample limits would defer rather than correct that behavior. Reimplementing eligibility in SQL would duplicate mutable rule semantics. A persisted asynchronous assessment snapshot would require versioning and invalidation across dated evidence, windows, reservations, official groups and participant mutations; it is unjustified merely to render an overview. There is no need to introduce a second write/recovery authority.

The chosen overview is explicitly partial for large plans. The oldest 25 IDs are a predictable preview, not a representative sample or a background sweep. Remaining participants stay unassessed until accessed through the owner workspaces; they are not silently skipped or marked complete. Exact totals require database work proportional to the indexed matching plan range. The bound is on hydrated models, retained IDs and costly canonical evaluations, not constant database CPU at arbitrary volume. No elapsed improvement is asserted without a comparable measurement.

Database fact updates can occur between the participant statement and later canonical evidence reads; this is the existing current-read observation model, not a write authorization token or an atomic global snapshot. Every write continues to reauthorize and lock its actual owner state. The overview never grants eligibility or authorizes an in-game transfer.

## Verification

A regression reproduces hydration of all 67 participants before the change. Corrected coverage tests require at most 25, an exact total of 67, 42 unassessed and an off-page manual blocker still counted. Further cases cover no double counting, canonical missing-evidence outcomes, clear and empty small plans, withdrawal, current authority revocation, cross-Alliance isolation, query limits/totals and preservation of incomplete coverage through the actual Assistant, Officer Brief and queued notification. Every supported locale retains the complete coverage parameters. The existing large participant browser journey checks that the dashboard cannot claim clearance before paginated readiness review.

Executed results and remaining management catalogue work belong to HARD-095 in the canonical delivery ledger. This ADR accepts the architecture, not full program completion or conditional merge readiness. Existing source, delivery-attempt, digest-member and endpoint-generation fences remain unchanged.
