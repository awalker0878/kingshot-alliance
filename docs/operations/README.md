# System operations

Status: Current

This area explains **how to run, observe, deploy and recover the application**. It is intentionally different from the business `Operations` bounded context under `app/Contexts/Operations`.

Use:

- [Runtime architecture](architecture.md)
- [Configuration](configuration.md)
- [Observability](observability.md)
- [Alliance Assistant](alliance-assistant.md)
- [Background processing](background-processing.md)
- [Deployment](deployment/README.md)
- [Runbooks](runbooks/README.md)
- [Recovery](recovery/README.md)
- [Release](release/README.md)

Production approval is governed separately by [Production approval](../governance/production-approval.md).

## Operational source-of-truth rule

Runbooks describe intent and safe procedure. Exact executable behavior lives in repository scripts, configuration and workflows. If a command in documentation diverges from `bin/*`, `config/*`, `Dockerfile` or the active workflow, fix the documentation rather than creating a second deployment mechanism.
