# Bounded contexts

Status: Current

Architecture V2 has seven write-owning business contexts:

- [Accounts](accounts/README.md)
- [GameWorld](game-world/README.md)
- [Alliance](alliance/README.md)
- [Operations](operations/README.md)
- [Intelligence](intelligence/README.md)
- [Communications](communications/README.md)
- [Platform](platform/README.md)

These documents define logical ownership. Physical source paths are mapped in [Codebase module map](../../codebase/module-map.md).

Do not add a peer context solely because a noun receives its own model, route, database table or folder.