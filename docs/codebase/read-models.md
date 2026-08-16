# Read models

Status: Current

`app/ReadModels` contains cross-context projections used when a screen/report naturally combines data from several write owners.

Current projection packages include:

- `AllianceDashboard`;
- `EventCalendar`;
- `EventHistory`;
- `EventManagement`;
- `KingdomIntelligence`;
- `KingdomSettings`;
- `SharedKingdomIntelligence`.

## Rules

ReadModels may compose data across contexts. They are read-only: no aggregate mutation, persistence-ownership transfer or compatibility behavior is allowed.

A ReadModel should make its data sources explicit and return a presentation/query contract rather than exposing writable source models to the controller/frontend.