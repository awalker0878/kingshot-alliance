# API reference

Status: Current high-level contract

The current versioned API is registered under `/v1` with the `throttle:api` middleware and scoped API credentials.

| Method/path | Required credential scope | Purpose |
| --- | --- | --- |
| `GET /v1/alliance` | `alliance:read` | Read permitted Alliance information. |
| `GET /v1/events` | `events:read` | Read permitted Event information. |
| `GET /v1/contributions` | `contributions:read` | Read permitted contribution information. |

Source: `routes/api.php` and `App\Contexts\Platform\Integrations`.

API credentials are scoped/revocable Platform integration credentials. They do not silently grant unrestricted Platform Administrator or in-game authority.

For public webhook event selectors see [Events](../events.md).