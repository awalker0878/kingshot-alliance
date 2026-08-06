# Deployment Runbook

## Release artifact

Deploy one immutable application image built from a reviewed commit. The same digest runs the PHP-FPM application, Nginx web entry point, Horizon worker, scheduler, and one-shot migration job.

Record:

- source commit
- image repository and SHA-256 digest
- application version
- build, test, and dependency evidence
- migration set
- previously approved rollback digest

Mutable tags such as `latest`, branch names, or release labels are not accepted by `bin/deploy`.

Each image owns its `bootstrap/cache` contents. Do not mount a persistent or shared volume over that path, clear it during deployment, or copy cache files between releases. This ensures a rollback digest uses the package manifest built into that exact image.

## Prepare staging

Create the environment file outside version control:

```bash
cp deploy/staging.env.example deploy/staging.env
```

Set a generated `APP_KEY`, a strong database password, the correct staging URL, and environment-specific integrations. Empty required values fail before deployment.

The default topology is `docker-compose.staging.yml`. It provides:

- `app` — PHP-FPM
- `web` — unprivileged Nginx on container port 8080 with runtime storage mounted read-only
- `worker` — Horizon
- `scheduler` — Laravel scheduler
- `release` — one-shot migrations
- private PostgreSQL and Redis services for the Phase 0 staging demonstration

Application roles that require runtime writes share the `storage` volume. The web-only role does not receive write access. Package and framework manifests remain inside the immutable image.

## Deploy by digest

```bash
ENV_FILE=deploy/staging.env \
STAGING_URL=https://staging.example.test \
./bin/deploy ghcr.io/owner/kingshot-alliance@sha256:<64-hex-digest>
```

The command:

1. Rejects mutable image references.
2. Validates the Compose and environment inputs.
3. Pulls the exact digest.
4. Starts PostgreSQL and Redis.
5. Creates a checksummed backup when replacing a running release.
6. Runs migrations once through the `release` service.
7. Replaces app, web, worker, and scheduler services with the same digest.
8. Requires both `/up` and `/health/ready` to pass.
9. Prints service state and recent logs on failure.

Set `STAGING_HTTP_PORT` when direct host-port exposure differs from 8080. Set `HEALTHCHECK_ATTEMPTS` to adjust the default 30 two-second attempts.

## CI staging demonstration

The container CI job builds the runtime image, validates source-control and build-context exclusions, launches the staging topology, runs migrations, verifies liveness and readiness, performs a checksummed backup and restore, verifies readiness again, and scans the image. This provides repeatable infrastructure-level acceptance evidence without claiming a production deployment.

## Production promotion

Production promotion must use the same image digest accepted in staging. Configuration may differ, but the image must not be rebuilt.

Use an environment-specific production orchestrator and managed data services where available. Preserve the same controls: digest-only images, image-owned package manifests, a single release job, least-privilege storage mounts, health gates, backup evidence, and an explicit rollback digest.

## Post-deployment

Observe the release through the agreed stabilization window. Record JSON logs, error rate, latency, queue depth, worker failures, and database health before closing the release.
