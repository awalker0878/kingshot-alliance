# GameWorld — Kingdoms

Status: Current — Architecture V3

Implementation target: `app/Contexts/GameWorld/Kingdoms`

Kingdoms owns neutral Kingdom identity and the game-world placement/reference facts needed to anchor Player and Alliance location without absorbing downstream Alliance/Operations/Intelligence policy.

## Boundary

Kingdom reference state exposes stable identifiers/current neutral facts. It does not own Alliance membership, Operations Event state or Intelligence observations merely because those records reference a Kingdom.