# Configuration reference

Status: Current summary

Authoritative configuration sources are `.env.example`, `deploy/staging.env.example`, `config/*.php` and hosted validation in `App\Contexts\Platform\Services\RuntimeConfigurationValidator`.

## Important groups

| Group | Representative values / rule |
| --- | --- |
| Application | `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_VERSION`, `RELEASE_SHA` |
| PostgreSQL | `DB_CONNECTION`, host/port/database/user/password, SSL mode |
| Redis | cache/queue/session connection, host/URL/auth/TLS, retry behavior |
| Sessions | Redis-backed hosted sessions, encryption, secure cookie, SameSite |
| Storage | private filesystem and content-media disk; production media uses S3 |
| Mail | mail transport/provider/sender settings |
| Observability | log channels/levels and operational correlation settings |
| Proxies/TLS | trusted proxies and explicit trust-all/loopback-staging controls |
| Workers | Horizon environment/supervisor sizing |
| Gift Codes | `GIFT_CODES_MODERATION`, `GIFT_CODES_APPROVED_SOURCE_INGESTION`, and `GIFT_CODES_NOTIFICATION_FANOUT` default off; evidence threshold, bounded fan-out/ingestion/catalogue limits, and maximum Governors per account are configurable |

## Hosted invariants

Staging/production require non-placeholder release identity, a valid application key, PostgreSQL, Redis-backed cache/queue/sessions and valid worker configuration. Production additionally requires debug off, HTTPS, secure cookies, S3-backed content media and encrypted PostgreSQL transport.

Never place real secret values in this document.
