# Intelligence — Ingestion

Status: Current — Architecture V3

Implementation target: `app/Contexts/Intelligence/Ingestion`

Ingestion owns acquisition, parsing and reconciliation of external intelligence inputs into Intelligence-owned observation/state contracts.

Ingestion does not mutate GameWorld, Alliance or Operations aggregates to reconcile imported facts. Cross-context effects use explicit owner contracts when a business command is actually required.