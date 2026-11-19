<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Endpoints
    |--------------------------------------------------------------------------
    */

    'base_url' => env('IDENTIQ_BASE_URL', 'https://identiq.prooffice.com.my/api'),

    'token_url' => env('IDENTIQ_TOKEN_URL', 'https://identiq.prooffice.com.my/oauth/token'),

    /*
    |--------------------------------------------------------------------------
    | OAuth client credentials
    |--------------------------------------------------------------------------
    |
    | Scopes are space separated. A token only carries scopes that your client
    | record also holds: onboarding:write, results:read, results:read_pii,
    | results:read_all.
    |
    */

    'client_id' => env('IDENTIQ_CLIENT_ID'),

    'client_secret' => env('IDENTIQ_CLIENT_SECRET'),

    'scopes' => env('IDENTIQ_SCOPES', 'onboarding:write results:read'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | Keep the timeout below the platform's idempotency lease (60 seconds by
    | default) so a retried request with the same key is never refused as
    | still running because of your own slow client.
    |
    */

    'timeout' => (int) env('IDENTIQ_TIMEOUT', 30),

    'connect_timeout' => (int) env('IDENTIQ_CONNECT_TIMEOUT', 10),

    /*
    | Retries for 429, 409 (idempotent request still running), 5xx and
    | connection errors. POSTs are retried with the same Idempotency-Key, so a
    | retry never creates a second journey.
    */
    'retry' => [
        'times' => (int) env('IDENTIQ_RETRY_TIMES', 2),
        'max_wait' => (int) env('IDENTIQ_RETRY_MAX_WAIT', 30),
    ],

    /*
    | Mutual TLS, when the platform requires it for your client. Paths to PEM
    | files; the certificate fingerprint must match the one registered.
    */
    'mtls' => [
        'cert' => env('IDENTIQ_MTLS_CERT'),
        'cert_password' => env('IDENTIQ_MTLS_CERT_PASSWORD'),
        'key' => env('IDENTIQ_MTLS_KEY'),
        'key_password' => env('IDENTIQ_MTLS_KEY_PASSWORD'),
    ],

    /*
    | Access tokens are cached (encrypted) until shortly before they expire.
    | null uses the default cache store.
    */
    'token_cache' => [
        'store' => env('IDENTIQ_TOKEN_CACHE_STORE'),
        'prefix' => 'identiq:token:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Secrets are comma separated. During a rotation, list both the new and
    | the old secret; a delivery is accepted when any secret matches either
    | X-Ekyc-Signature or X-Ekyc-Signature-Previous.
    |
    */

    'webhooks' => [
        'enabled' => (bool) env('IDENTIQ_WEBHOOKS_ENABLED', true),

        'path' => env('IDENTIQ_WEBHOOK_PATH', 'identiq/webhook'),

        'middleware' => [],

        'secrets' => env('IDENTIQ_WEBHOOK_SECRETS', ''),

        // Seconds a delivery timestamp may differ from this server's clock.
        'tolerance' => 300,

        // Deliveries are retried for 24 hours and can be resent by an admin,
        // all with the same event_id. Seen ids are remembered this long.
        'dedupe' => [
            'enabled' => true,
            'store' => env('IDENTIQ_WEBHOOK_DEDUPE_STORE'),
            'ttl' => 60 * 60 * 24 * 7,
        ],
    ],

];
