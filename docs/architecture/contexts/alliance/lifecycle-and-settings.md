# Alliance lifecycle and settings

Status: Current  
Context: Alliance  
Implementation: `app/Contexts/Alliance/Core`

## Purpose

Own the Alliance tenant's core business state and settings used by Alliance-owned capabilities.

## Responsibilities

- Alliance creation/core lifecycle behavior implemented in the context;
- Alliance settings/profile state;
- Alliance overview/application entry points owned by Alliance;
- coordination with neutral GameWorld references rather than duplicating GameWorld identity state.

## Invariants

Alliance lifecycle writes must use Alliance mutation authority for the active Player and concrete Alliance scope. Platform lifecycle/entitlement actions may orchestrate platform concerns around an Alliance, but they do not become the owner of Alliance membership or in-game authority.

Cross-context dashboards should use ReadModels when they combine Alliance state with Operations or Intelligence.