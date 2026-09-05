# Research-backed acquisition implementation status

This branch implements the research-backed Gift Code acquisition architecture for a fresh deployment. Compatibility shims and legacy migrations are intentionally excluded.

Current implementation slices are tracked in `docs/product/gift-code-research-backed-acquisition-plan.md`. The branch introduces explicit synchronization state, push-delivery/subscription domain primitives, conservative publication extraction and source-health/usefulness semantics. Provider-specific transports and base-schema wiring are implemented in subsequent commits on this branch.