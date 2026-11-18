<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User-Agent
    |--------------------------------------------------------------------------
    |
    | Discogs requires a unique User-Agent on every request, ideally in
    | "AppName/1.0 +https://example.com" form. Generic agents may be silently
    | blocked. When null, one is derived from app.name and app.url.
    |
    */

    'user_agent' => env('DISCOGS_USER_AGENT'),

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | This package never sends an unauthenticated request. The default client
    | picks the first complete set of credentials in this order:
    |
    |   1. OAuth 1.0a  - consumer key/secret + oauth token/secret (acts as that user)
    |   2. Token       - personal access token (acts as the token's owner)
    |   3. Consumer    - consumer key/secret only (authenticated app, no user)
    |
    | Per-user credentials can be supplied at runtime with Discogs::withToken()
    | and Discogs::withOAuth().
    |
    */

    'token' => env('DISCOGS_TOKEN'),

    'consumer_key' => env('DISCOGS_CONSUMER_KEY'),
    'consumer_secret' => env('DISCOGS_CONSUMER_SECRET'),

    'oauth_token' => env('DISCOGS_OAUTH_TOKEN'),
    'oauth_token_secret' => env('DISCOGS_OAUTH_TOKEN_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Default username
    |--------------------------------------------------------------------------
    |
    | Used by user-scoped endpoints (collection, wantlist, inventory...) when
    | no username is passed. When null it is looked up once per client from
    | /oauth/identity.
    |
    */

    'username' => env('DISCOGS_USERNAME'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | text_format controls how text fields (profiles, notes) are returned:
    | "discogs" (markup, the default), "plaintext" or "html".
    |
    */

    'base_url' => env('DISCOGS_BASE_URL', 'https://api.discogs.com'),
    'text_format' => env('DISCOGS_TEXT_FORMAT', 'discogs'),
    'timeout' => (int) env('DISCOGS_TIMEOUT', 30),
    'connect_timeout' => (int) env('DISCOGS_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    |
    | Authenticated requests are limited per source IP over a moving 60 second
    | window. By default a 429 throws RateLimitException straight away, which
    | suits web requests. Set "retries" above 0 to have the client wait
    | (Retry-After when present, otherwise "wait" seconds) and try again;
    | each retry blocks the current process, so prefer it in queued jobs
    | and console commands.
    |
    | With "throttle" on, the client also pauses for "wait" seconds before a
    | request when the last response reported no remaining requests.
    |
    */

    'rate_limit' => [
        'retries' => (int) env('DISCOGS_RATE_LIMIT_RETRIES', 0),
        'wait' => (int) env('DISCOGS_RATE_LIMIT_WAIT', 60),
        'throttle' => (bool) env('DISCOGS_THROTTLE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retrying failed reads
    |--------------------------------------------------------------------------
    |
    | GET requests that fail with a connection error or a 5xx response are
    | retried up to "times" times, waiting "sleep" milliseconds multiplied by
    | the attempt number in between. Writes are never retried, since they may
    | have taken effect on Discogs' side.
    |
    */

    'retry' => [
        'times' => (int) env('DISCOGS_RETRY_TIMES', 2),
        'sleep' => (int) env('DISCOGS_RETRY_SLEEP', 500),
    ],

];
