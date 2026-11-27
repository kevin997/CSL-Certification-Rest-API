# Academy WhatsApp sender

The release is off by default. To enable it, provide a dedicated internal WAHA
Core gateway with multi-session support (2026.6.1 or newer; pin a tested image)
and a durable session store. Do not add client sessions to the existing
platform WhatsApp or Eshu instances. Keep the gateway inaccessible from public
networks and put the API key only in the API/worker configuration.

- `WAHA_TENANT_ENABLED=true`
- `WAHA_TENANT_BASE_URL` set to the private gateway origin
- `WAHA_TENANT_API_KEY` configured as a secret

Before enabling for real accounts, use two test academies and unshared
test handsets. Verify owner-only pairing, wrong-host rejection, one account
per academy, restart persistence, contact lookup, unlinked
admin fallback only when enabled, and fallback after licence expiry/refund.
Use test recipients; do not replay historical payments. QR endpoints must
never be publicly cached or served by the gateway directly to a browser.

References: [WAHA sessions](https://waha.devlike.pro/docs/how-to/sessions/),
[WAHA storage](https://waha.devlike.pro/docs/how-to/storages/),
[WAHA security](https://waha.devlike.pro/docs/how-to/security/).
