# ADR 0009 — Azure Container Apps runtime topology

- **Status:** Accepted
- **Date:** 2026-08-12
- **Owners:** Platform / Operations
- **Related scope:** Hosted Azure Container Apps runtime
- **Supersedes:** None

## Context

Kingshot Alliance ships one immutable runtime image containing PHP-FPM, the Laravel application, Nginx, built frontend assets, and the generic `kingshot-entrypoint`. Docker Compose runs PHP-FPM and Nginx as separate services and routes FastCGI traffic to the Compose service name `app:9000`.

Azure Container Apps supports multiple tightly coupled containers in one Container App replica. Nginx and PHP-FPM are one web-serving unit: they revise, scale, and fail together. Horizon, scheduler execution, migrations, and future persistent workers have different lifecycle and scaling requirements and should not be coupled to web replica count.

A single-container web process supervisor or shell wrapper would add process-management responsibility to the image and would make the web role different from the other roles that already reuse the generic entrypoint.

## Decision

Use one immutable application image for every Laravel runtime role.

The Azure Container Apps web application contains two containers in each replica:

- `nginx` runs `nginx -c /etc/nginx/azure.conf -g "daemon off;"`, listens on port 8080, and is the only container reached by Container Apps HTTP ingress.
- `app` does not override the image startup command. It therefore runs `kingshot-entrypoint` followed by the image default `php-fpm` command and listens for FastCGI on port 9000.

The ACA-specific Nginx profile uses `fastcgi_pass 127.0.0.1:9000;` because both containers share the replica network namespace. The existing Compose Nginx profile remains separate and continues to use `fastcgi_pass app:9000;` because Compose services use service-name networking.

Container Apps ingress terminates external HTTPS, rejects insecure public traffic, and forwards HTTP to Nginx on target port 8080. PHP-FPM port 9000 is not exposed as application ingress.

Other roles use separate Azure deployment units while reusing the same immutable image:

- Horizon runs in its own Container App so queue capacity can scale independently of web replicas.
- Laravel scheduler execution runs as a scheduled Container Apps Job invoking `php artisan schedule:run`.
- Database migrations run as a manual/release Container Apps Job invoking `php artisan migrate --force` exactly once per release.
- Laravel Pulse remains disabled until its schema and access policy are introduced; if a persistent Pulse worker is later approved, it receives its own Container App.

When Azure overrides a container startup command, roles that require Laravel runtime validation must invoke `kingshot-entrypoint` explicitly before the role command. The PHP-FPM web container intentionally does not override startup, so validation runs automatically.

## Consequences

### Positive

- Nginx and PHP-FPM remain one-process containers while scaling as one tightly coupled web replica.
- Horizon, scheduler execution, migrations, and future workers retain independent lifecycle and scaling boundaries.
- The same reviewed image digest and embedded release metadata are reused across every runtime role.
- Docker Compose and Azure Container Apps can use different FastCGI addressing without one environment breaking the other.
- No in-container process supervisor or web startup wrapper is required.

### Negative or trade-off

- The web Container App pulls the same application image twice per replica, including PHP components that the Nginx container does not execute.
- Nginx and PHP-FPM resource requests must be sized together to satisfy Container Apps replica CPU/memory constraints.
- Runtime-created files are not assumed to synchronize through the containers' writable image layers; durable/shared data requires an explicit storage service or shared volume.
- Azure role command overrides must preserve the validation entrypoint intentionally.

## Supported boundaries affected

- Runtime image: `Dockerfile`, `docker/entrypoint.sh`, `docker/nginx/default.conf`, and `docker/nginx/azure.conf`.
- Hosted deployment: Azure Container Apps web, Horizon, scheduler-job, and migration-job definitions.
- Runtime configuration: PostgreSQL and Redis remain external dependencies; Azure Managed Redis uses the application `REDIS_SCHEME=tls` configuration with its private endpoint.
- Operations: [Deployment runbook](../operations/runbooks/deployment.md) and [runtime configuration reference](../operations/configuration-reference.md).

## Validation

- Build the runtime image and require `nginx -t -c /etc/nginx/azure.conf` to pass during the Docker build.
- Keep architecture tests that assert the Compose profile targets `app:9000`, the ACA profile targets `127.0.0.1:9000`, and the image preserves `ENTRYPOINT ["kingshot-entrypoint"]` / `CMD ["php-fpm"]`.
- In Azure, require the web revision to become healthy with ingress target port 8080 and verify `/up` and `/health/ready` over HTTPS.
- Verify the Nginx container can reach PHP-FPM over loopback and that port 9000 is not externally exposed.
- Verify Horizon and release/scheduler jobs use the same immutable release digest as the web containers.

## Revisit when

- Nginx and PHP-FPM no longer need to scale or revise together.
- Image pull/storage overhead from using the same image twice becomes material enough to justify separate runtime image targets.
- Azure Container Apps changes its multi-container networking or command semantics.
- A different web runtime removes the Nginx-to-FPM FastCGI boundary.

## Supersession handling

None. If the hosted runtime topology changes materially, create a replacing ADR and mark this record `Superseded` rather than rewriting the decision history.
