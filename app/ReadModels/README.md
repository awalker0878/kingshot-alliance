# Compositional read models

Cross-context dashboards, history, analytics and reporting projections belong here when no single write-owning context naturally owns the view.

ReadModels may compose data from multiple contexts. They are read-only: they do not mutate aggregates or take persistence ownership from a bounded context.
