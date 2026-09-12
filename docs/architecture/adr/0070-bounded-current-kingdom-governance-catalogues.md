# ADR-0070: Bounded current Kingdom Governance catalogues

Status: Accepted

## Context

Role management, assignments and permission-holder projections materialized whole Kingdom catalogues. Selectors depended on those lists, and history exposed arbitrary audit metadata and operator identity. Health loaded every custom role and repeated the Operations system-role policy. These reads grew with retained history and coupled projection code to permission semantics it does not own.

## Decision

GameWorld retains Governance mutations, Operations declares its own default-role permission policy, Workflows compose the owners, and ReadModels own authorized presentation. The GET role-management adapter moves into KingdomGovernance; POST and DELETE remain GameWorld adapters. Health reads only the three system roles, uses SQL existence/count facts and the authoritative Operations policy, and treats current canonical administrator assignments as authority.

Roles, assignments, distinct permission holders, Governor/role choices and audit history use independent 25-row keyset pages with a 26th-row continuation probe. Encrypted cursors bind current actor, Kingdom, catalogue kind, filters and captured upper ID frontier. Every request rechecks current canonical actor authority. Totals are SQL counts; duplicate grants never multiply holder rows. Holder cards show a count and link to the complete filtered assignment catalogue, avoiding unbounded embedded role arrays. Permissions are bounded by the existing 500-entry owner registry contract and fail closed beyond it. Baseline indexes support Kingdom/page, active role/player choices, assignment filters and Kingdom audit traversal.

Choices search the full current scope and resolve one selected identity independently of the current page. Forms retain drafts and selections during paging and retry; actor/Kingdom changes clear intent and fence older responses. Role, assignment, holder and history pages navigate independently. Bulk intent remains explicitly bounded to 50 selected Governors, with preview invalidation after intent changes and current owner revalidation on commit. Visible errors and recovery controls use all 17 supported locales.

History materializes only a bounded allowlist of public Governance receipt fields in SQL. Operator account IDs, names, arbitrary diagnostics and recovery reasons do not enter the Kingdom response. Assignment reasons are projected only for assignments attributed to a Governor; Platform recovery reasons remain private. Normal Governor-authored assignment reasons remain visible. Health and presentation reads do not repair state.

## Verification

GovernanceCataloguePagesTest covers complete traversal of six catalogues, query/page budgets, duplicate-heavy holders, off-page choices, finite frontiers, search and current actor/Kingdom/filter isolation, lost authority and private projection fields. KingdomGovernanceHealthBoundsTest covers more than 1,000 custom roles, canonical authority and actual owner reconciliation. Twelve frontend loader cases cover bounded state, stale responses and recoverable failures. The desktop/mobile browser journey exercises actual catalogue and selector navigation, failed-page retry, retained drafts and successful assignment. Hosted PostgreSQL and browser execution are required in the delivery ledger before completion.
