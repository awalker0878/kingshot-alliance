# Alliance context

Status: Current  
Implementation: `app/Contexts/Alliance`

Alliance owns the cohesive Alliance tenant and member-management capability space.

## Capabilities

- [Lifecycle and settings](lifecycle-and-settings.md)
- [Membership and authority](membership-and-authority.md)
- [Recruitment](recruitment.md)
- [Content and media](content.md)

Implementation modules are `Core`, `Membership`, `Access`, `Recruitment`, `Content` and Alliance-owned `Policies`.

## Authority

Alliance game authority is Player-scoped. The active Player's current active membership, rank and specialist roles determine Alliance authority. Authority is not aggregated across a User's other Players, and Platform Administrator is not a bypass.

Alliance may expose current membership/governance facts to Operations and Intelligence. Those consumers own interpretation of their own permission semantics.