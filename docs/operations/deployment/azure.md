# Azure/container deployment

Status: Current deployment model

The repository's hosted model targets containerized application execution with managed/data services appropriate to the environment. Current application requirements are PostgreSQL, Redis, durable production object storage, HTTPS ingress and runtime secret injection.

## Application components

- immutable web application image;
- worker/Horizon execution using the same release image;
- migration/release command execution from the same release identity;
- PostgreSQL 18-compatible database service;
- Redis service suitable for cache, sessions and queues;
- private S3-compatible media/object storage in production;
- managed secret injection rather than baked image secrets.

## Deployment principles

- build once and deploy the same immutable image identity across release steps;
- avoid public exposure of data services when private networking is available/required;
- make DNS/private-endpoint dependencies explicit;
- do not override baked `APP_VERSION`/`RELEASE_SHA` with unrelated runtime values;
- validate TLS, trusted proxies and secure cookies from the deployed environment;
- test webhook egress policy against metadata/private/management destinations;
- scale web and worker capacity independently while keeping Redis/PostgreSQL limits in view.

Exact Azure CLI/resource commands are intentionally not duplicated here because they drift faster than the repository/runtime contract. Environment automation should remain in infrastructure/deployment tooling and be reviewed together with this operational model.