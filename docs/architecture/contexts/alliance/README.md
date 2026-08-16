# Alliance context

Status: Current  
Implementation: `app/Contexts/Alliance`

## Purpose

Alliance owns the cohesive Alliance tenant and member-management capability space.

## Capabilities

- `Core` — Alliance lifecycle/settings and core tenant state;
- `Membership` — Player membership, invitations and membership lifecycle;
- `Access` — R1–R5/specialist role permissions and mutation authority;
- `Recruitment` — candidate/application workflows;
- `Content` — Alliance-authored/public/member content and media;
- `Policies` — Alliance-owned policy boundary helpers.

## Authority

Alliance game authority is Player-scoped. The active Player's current active membership, rank and specialist roles determine Alliance authority. Authority is not aggregated across a User's other Players, and Platform Administrator is not a bypass.

## Cross-context responsibilities

Alliance may expose current membership/governance facts to Operations and Intelligence. Those consumers own interpretation of their own permission semantics.