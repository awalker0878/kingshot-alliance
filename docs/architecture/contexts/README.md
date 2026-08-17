# Bounded contexts

Status: Current — Architecture V3

Architecture V3 has exactly seven write-owning business contexts:

- [Accounts](accounts/README.md)
- [GameWorld](game-world/README.md)
- [Alliance](alliance/README.md)
- [Operations](operations/README.md)
- [Intelligence](intelligence/README.md)
- [Communications](communications/README.md)
- [Platform](platform/README.md)

Each context is physically organized by cohesive business capabilities. Technical layers such as Actions, Models, Queries, Services, Policies and Http live inside those capability packages.

`app/Workflows`, `app/ReadModels` and `app/Shared` are not additional bounded contexts.

Do not create a peer context merely because a noun has a model/table/route, and do not create a capability merely to mirror every noun. A capability represents cohesive business behavior inside its context.