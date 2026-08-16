# Shared technical layer

Only genuinely cross-cutting technical contracts and infrastructure belong here. Infrastructure packages live below `App\Shared\Infrastructure`; small access and HTTP contracts may live directly below Shared when they remain business-neutral.

Shared does not import business contexts, Workflows or ReadModels. Feature aggregates and game/Alliance policy belong to their owning bounded context.
