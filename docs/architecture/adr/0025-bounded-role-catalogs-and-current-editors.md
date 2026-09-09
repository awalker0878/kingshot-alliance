# ADR-0025: Bound role catalogs and keep editors aligned with current roles

Status: Accepted

## Context

HARD-051 found omitted permission input and generated keys that could overflow storage. HARD-052 found three unbounded role catalogs, including a management query with a membership-count query per role and a dashboard selector offering archived roles. HARD-053 found one-time draft initialization that fails when Inertia retains the page and returns a newly created role; row validation errors were also not displayed.

## Decision

Alliance Access owns AllianceRoleCatalogQuery. Management and assignment options return at most 25 roles, with one lookahead row. Cursors use the maintained encrypted ScopedCursorCodec and bind the Alliance, active/archive filter and normalized name-prefix search. Ordering uses the immutable Alliance-local unique role key, so a rename does not move a role across page boundaries. Options always exclude archived roles.

Management eager-loads permission facts and counts assignments with a correlated, Alliance-scoped query using the existing assignment index. This takes two queries regardless of the page's role count. Active/archive partial indexes support cursor traversal; a lower-name pattern index supports literal case-insensitive prefix searches. The fresh schema owns these indexes directly.

The role-management controller authorizes the current Alliance scope before reading. Its options endpoint applies the same current role-management authority and a separate 60-request-per-minute account budget. It does not accept an Alliance ID as authority. Dashboard and bulk projections no longer fetch whole catalogs; their role pickers request bounded options when used. Search and page changes retain an explicitly selected role's label without accumulating all visited pages. Aborted/superseded requests cannot replace newer results, and requests are aborted on unmount.

Management exposes separate active/archive pages and a name-prefix filter. Each editable role owns a maintained Inertia form keyed by role ID. New rows initialize their own form; unchanged rows preserve unsaved edits, successful writes adopt current server values, and row name/permission/role errors and processing state remain visible.

HTTP create/update requires an explicit distinct list from the closed permission vocabulary. An empty list is valid; omitted input does not silently revoke permissions. Owners enforce the 100-character display-name and 64-character generated-key storage bounds before persistence. Stable keys remain unchanged on rename.

## Alternatives and consequences

A fixed role-creation quota was rejected because bounded reads and usable pagination solve the read cost without introducing a new product entitlement. Truncating dropdowns was rejected because roles beyond the cutoff would become inaccessible. A catalog cache would duplicate mutable scope/permission state and require invalidation; the bounded authoritative read is sufficient.

The picker remains advisory. Role creation, updates, assignment, removal and archival keep their current transactional authorization and delegation checks. A role selected before another officer archives it can still be rejected by the owner at commit.

The shared PageSlice/ScopedCursorCodec mechanisms are retained. There is no parallel permission authority, compatibility payload, legacy catalog fallback or additional role persistence.

## Verification

AllianceRoleInputBoundaryV3Test covers malformed create/update payloads, explicit empty permissions and owner storage bounds without partial audit/outbox changes. AllianceRoleCatalogV3Test covers bounded traversal, constant query counts, current assignment counts, renamed rows, cross-scope/filter and tampered cursors, archived exclusion, literal search characters and real HTTP authority/consumer contracts.

AllianceRoleManagement.spec.ts exercises creation, immediate editing, validation feedback, rename and archive on a retained page, plus option paging/search and selection preservation in desktop/mobile browsers. Frontend type/style, accessibility, localization, receipts, build and performance gates remain required alongside PostgreSQL/architecture verification.
