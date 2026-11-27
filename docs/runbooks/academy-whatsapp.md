# Academy WhatsApp sender

The release is off by default. To enable it, provide a dedicated internal WAHA
Core gateway running the GOWS engine with multi-session support (2026.6.1 or newer; pin a tested image digest)
and a durable session store. Do not add client sessions to the existing
platform WhatsApp or Eshu instances. Keep the gateway inaccessible from public
networks. Do not publish a host port or expose the WAHA Dashboard or Swagger; put the API key only in the API/worker configuration.

- `WAHA_TENANT_ENABLED=true`
- `WAHA_TENANT_BASE_URL` set to the private gateway origin
- `WAHA_TENANT_API_KEY` configured as a secret

## BootCamps pilot on Coolify

The production Compose descriptor uses a dedicated GOWS gateway, a separate
session volume and a bridge shared only with KURSA's app and worker. Explicit
pilot scope is academy ID 15. Keeping the Compose feature flag false
(`WAHA_TENANT_ENABLED=false`) disables academy sending and pairing without
discarding the session volume or altering the platform sender.

Before deployment, provision a fresh 64-character hexadecimal key in the
host's `/opt/kursa-academy-whatsapp/api-key` file. Make the file readable by
root and the app/worker group (GID 33), with mode 0640; do not print it or
commit it. Compose mounts the file read-only for those roles and the
gateway. The app reads it at runtime and does not put it in the config cache.

Before enabling for real accounts, use two test academies and unshared
test handsets with their owners’ consent. Verify owner-only pairing, wrong-host rejection, one account
per academy, restart persistence, contact lookup, unlinked
admin fallback only when enabled, and fallback after licence expiry/refund.
Use test recipients; do not replay historical payments. QR endpoints must
never be publicly cached or served by the gateway directly to a browser.

References: [WAHA sessions](https://waha.devlike.pro/docs/how-to/sessions/),
[WAHA storage](https://waha.devlike.pro/docs/how-to/storages/),
[WAHA security](https://waha.devlike.pro/docs/how-to/security/).
