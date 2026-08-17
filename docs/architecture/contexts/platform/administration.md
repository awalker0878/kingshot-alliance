# Platform — Administration

Status: Current — Architecture V3

Implementation target: `app/Contexts/Platform/Administration`

Administration owns Platform Administrator grants/access and platform-wide administrative behavior that is not game-domain authority.

## Authority boundary

Platform Administrator is User-scoped. It does not grant Alliance membership, Kingdom governance authority or Operations/Intelligence permissions.