# V2 compositional read models

Cross-context dashboards, history, analytics and reporting projections belong here when they do not naturally belong to one write-owning context.

Read models may compose data from multiple contexts. They are read-only: no aggregate mutation, persistence ownership transfer, or compatibility behavior is allowed. V2 read models must not import `App\Domain\*`.