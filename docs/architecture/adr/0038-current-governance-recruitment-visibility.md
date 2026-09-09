# ADR-0038: Current Recruitment visibility in governance history

Status: Accepted

## Context

The governance timeline admits MembershipManage, RoleManage or Manage. Those permissions do not imply RecruitmentManage. Returning raw recruitment audit metadata through governance history or the Assistant therefore exposed private source, tags and review details outside the Recruitment authority boundary.

## Decision

AllianceGovernanceTimelineQuery requires a current viewer Player ID alongside the Alliance scope. It enforces existing governance admission itself, and excludes Recruitment events unless the viewer currently has RecruitmentManage. This SQL predicate applies before user-selected filters, cursor boundaries and page limits. A recruitment-only filter cannot bypass the exclusion.

The HTTP and Assistant adapters pass their current Player identity into this single projection. There is no viewer-less fallback and RecruitmentManage alone does not grant governance admission. Revocation applies on each subsequent read, including continuation through a cursor obtained while authorized. Authorized recruitment auditors retain the current private metadata required by the product, subject to candidate retention in ADR-0037.

## Consequences

Broader officer permissions no longer reveal recruitment-private metadata through a composed read. Page counts and continuations reflect only visible events. Six behavior cases cover direct/HTTP/Assistant consumers, both permission combinations, filtering before limits, current revocation of either permission, and cross-Alliance scope. Existing timeline ordering and filter cases now supply viewer identity.
