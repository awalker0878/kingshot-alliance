# Authority model

Status: Current — Architecture V3

The application separates account identity from game-domain authority.

```text
User
├── account assurance / authentication
├── Platform Administrator grant (Platform only)
└── owns many Players by scalar user_id
      |
      └── Active Player
          ├── Alliance membership / rank / specialist roles
          ├── Kingdom governance roles
          ├── Operations authority inputs
          └── Intelligence authority inputs
```

## Account principal

Accounts `Identity` owns User identity. Authentication, credentials, verification and MFA establish/assure the operating User account.

## Game principal

`GameWorld/Players` owns Player identity and active Player selection. The active Player is the security principal for game-domain behavior.

A User may own multiple Players. Authority is never unioned across those Players.

## Ownership reference

Player ownership is represented through scalar `user_id` and supported GameWorld ownership queries/contracts. GameWorld Player does not expose an Eloquent relationship into Accounts User.

## Alliance authority

`Alliance/Access` interprets Alliance permissions using the active Player's current Alliance membership, R1–R5 rank and specialist roles in the concrete Alliance.

## Kingdom authority

`GameWorld/Governance` interprets GameWorld Kingdom governance permissions using current Player-scoped governance assignments in the concrete Kingdom.

## Operations authority

`Operations/Access` owns Operations permission vocabulary. It may consume Alliance/GameWorld facts but decides what those facts authorize for Operations capabilities.

## Intelligence authority

`Intelligence/Access` owns Intelligence permission vocabulary and interpretation.

## Platform authority

`Platform/Administration` owns Platform Administrator authority. It is User-scoped and never bypasses game-domain authorization.

## Mutable write authorization

Request-time actor resolution is not sufficient for a write whose authority can change concurrently. The owning capability Action must revalidate mutable scope/role state inside its write transaction after acquiring the locks required by that owner.

Authorization services interpret permissions; lock acquisition belongs to the owner write path.