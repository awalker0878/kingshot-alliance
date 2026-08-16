# Alliance context

Status: Current — Architecture V3

Implementation target: `app/Contexts/Alliance`

Alliance owns Alliance tenant lifecycle, membership/leadership, Alliance access semantics, recruitment and Alliance content.

## Capabilities

```text
Alliance/
├── Lifecycle/
├── Membership/
├── Access/
├── Recruitment/
└── Content/
```

- **Lifecycle** owns Alliance creation, lifecycle and settings.
- **Membership** owns Player membership, invitations and R1–R5 leadership behavior.
- **Access** owns Alliance permission vocabulary, specialist roles and authorization interpretation.
- **Recruitment** owns applications, recruiting and review behavior.
- **Content** owns Alliance content/media.

## Policies

`Alliance/Policies` is not a V3 capability or context-root technical bucket. A policy belongs under the capability that owns the business rule.

## Authority

Alliance game authority is Player-scoped. The active Player's current membership, rank and specialist roles determine Alliance authority for the concrete Alliance. Authority is never aggregated across a User's other Players, and Platform Administrator is not a bypass.

## Cross-context boundary

Alliance may expose current membership/rank/role facts through supported owner queries. Operations and Intelligence interpret those facts using their own permission semantics rather than navigating Alliance persistence directly.