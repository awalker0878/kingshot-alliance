# ADR-0067: Bounded lasting Kingdom administrator handoff

Status: Accepted

## Context

Administrator handoff revoked every effective actor assignment in an unbounded model loop, while future-effective grants survived replacement. It also recorded unchanged additive or self handoffs. Both handoff and Platform recovery could reuse a temporary target grant, leaving the Kingdom without administration when it expired. Last-administrator checks counted aliases or Governors who had moved away and could remove the last lasting grant in favor of a temporary one.

## Decision

GameWorld retains one transaction for administrator handoff, including current exclusive Kingdom/actor authority, the current direct target, assignment changes and audit/outbox. Replacement selects at most 501 unrevoked, unexpired actor assignment IDs, including future-effective grants. More than 500 rejects the whole operation before granting target authority. At most 500 are revoked in one bounded SQL update. The operator can first add the replacement without removing the actor, then use ordinary assignment removal (including scheduled assignment IDs) to reduce the remaining replacement set. This is the same reachable bounded repair contract as ADR-0063.

Handoff to another Governor and Platform recovery establish a current non-expiring target assignment. A temporary or scheduled assignment does not substitute for lasting administration. Repeating an additive handoff with a lasting target produces no additional audit/outbox effect. A self handoff returns the existing current administrator assignment without changing it. Replacement retries still require current actor authority. Reasons share the 500-Unicode-character owner bound.

Current administrator queries require effective roles, a matching Kingdom and a direct Governor still in that Kingdom. Removing an effective lasting administrator assignment requires another current lasting assignment. Temporary assignment admission evaluates the surviving Governor at the proposed expiry, with current canonical identity and role state. Bulk previews use the same lasting-survivor facts; their eventual owner calls revalidate before mutation. No permission meaning moves out of GameWorld, and Platform recovery retains its existing protected Workflow composition.

## Verification

KingdomHandoffBoundsTest adds 13 cases for 501-assignment rejection, additive cleanup and a successful 500-assignment replacement, future-grant revocation, maximum reasons, self/additive replay, lasting target authority after temporary expiry in both handoff and recovery, alias/foreign/oversized input, late audit/outbox rollback and retry, and ineligible or temporary last-administrator survivors. Existing Governance and recovery tests remain required. HARD-125 records executed evidence.
