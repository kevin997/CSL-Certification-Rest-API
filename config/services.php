<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN', '2cc2bfdb-b6a5-4543-8466-3adfcf063045'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mailjet' => [
        'key' => env('MAILJET_APIKEY'),
        'secret' => env('MAILJET_APISECRET'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN', default: 'AAGW4ZsUYSxeny5LbNFDW7rOmiVdKuVbnWA'),
        'chat_id'   => env('TELEGRAM_CHAT_ID', default: "-1001836815830"),
    ],

    'media_service' => [
        'url' => env('MEDIA_SERVICE_URL'),
        'secret' => env('MEDIA_SERVICE_SECRET'),
    ],

    'bunny_stream' => [
        // Master switch. When false (or credentials missing), video uploads fall
        // back to the self-hosted media service.
        'enabled' => env('BUNNY_STREAM_ENABLED', false),
        // Numeric Video Library ID from the Bunny dashboard.
        'library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
        // Video Library API key (Stream → API).
        'api_key' => env('BUNNY_STREAM_API_KEY'),
        // Pull-zone hostname serving the HLS, e.g. "vz-xxxxxxxx.b-cdn.net".
        'cdn_hostname' => env('BUNNY_STREAM_CDN_HOSTNAME'),
        // Token Authentication key of the pull zone (used to sign playlist URLs).
        'token_auth_key' => env('BUNNY_STREAM_TOKEN_AUTH_KEY'),
        // Shared secret we require on the Bunny → API webhook (Bunny doesn't sign).
        'webhook_secret' => env('BUNNY_STREAM_WEBHOOK_SECRET'),
        // Optional collection to file uploads under.
        'collection_id' => env('BUNNY_STREAM_COLLECTION_ID'),
    ],

    'livekit' => [
        'api_key' => env('LIVEKIT_API_KEY'),
        'api_secret' => env('LIVEKIT_API_SECRET'),
        'server_url' => env('LIVEKIT_SERVER_URL', 'ws://localhost:7880'),
    ],

    'ipgeolocation' => [
        'api_key' => env('IPGEOLOCATION_API_KEY', 'f632e06f3f6a489299101706b1a2fa32'),
    ],

    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
        'subject' => env('VAPID_SUBJECT', ''),
    ],

    'kafka' => [
        'brokers' => env('KAFKA_BROKERS', 'localhost:29092'),
        'consumer_group' => env('KAFKA_CONSUMER_GROUP', 'csl-certification-consumer'),
        'purchase_topic' => env('KAFKA_PURCHASE_TOPIC', 'purchase.completed'),
    ],

    // WhatsApp sending via the Wachap API (same platform account as shopikat).
    'wachap' => [
        'base_url' => env('WACHAP_API_BASE_URL', 'https://api.wachap.com'),
        'token' => env('WACHAP_API_TOKEN'),
        'account_id' => env('WACHAP_ACCOUNT_ID'),
        // Human-like pacing bounds for bulk sends (see App\Support\WhatsAppThrottle).
        'min_delay_ms' => env('WACHAP_MIN_DELAY_MS', 4000),
        'max_delay_ms' => env('WACHAP_MAX_DELAY_MS', 15000),
        // Defaults for postStatus() broadcasts.
        'status_bg' => env('WACHAP_STATUS_BG', '#4F46E5'),
        'status_font' => (int) env('WACHAP_STATUS_FONT', 1),
        'validate_numbers' => (bool) env('WACHAP_VALIDATE_NUMBERS', false),
        'groups' => [
            'support' => env('WACHAP_GROUP_SUPPORT'),
        ],
    ],

    // Self-hosted Ollama server used for AI-generated marketing/retention copy.
    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://31.97.75.62:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen2.5:7b'),
        'fallback_model' => env('OLLAMA_FALLBACK_MODEL', 'llama3.1:8b'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 300),
        'num_ctx' => (int) env('OLLAMA_NUM_CTX', 8192),
    ],

    // Company blog (WordPress REST) — primary source for marketing tips/tutorials.
    // Primary source for KURSA tips/guides/email campaigns: the KURSA
    // resources site (product tutorials). WordPress application passwords are
    // stored here per operator instruction.
    'blog' => [
        'url' => env('BLOG_URL', 'https://resources.csl-brands.com'),
        'username' => env('BLOG_USERNAME'),
        'app_password' => env('BLOG_APP_PASSWORD', 'FWzV LNpQ 6M6n pVdu qCNe tf1V'),
    ],

    // The general CSL blog — source for WhatsApp STATUS posts, which are
    // published by the openclaw agent (VPS 31.97.179.151), NOT by this app's
    // scheduler (kursa:post-daily-status stays available for manual use).
    'status_blog' => [
        'url' => env('STATUS_BLOG_URL', 'https://blog.csl-brands.com'),
        'username' => env('STATUS_BLOG_USERNAME'),
        'app_password' => env('STATUS_BLOG_APP_PASSWORD', 'Sh5e Dbbs knC4 DAUz k9Uo BeJO'),
    ],

    // Marketing content engine.
    'marketing' => [
        // Documents FeatureInventoryService may mine for sellable "features".
        // Paths are relative to base_path(). Deliberately EMPTY by default:
        // everything under docs/ is internal engineering material, and mining it
        // produced developer jargon in customer-facing WhatsApp broadcasts. Add a
        // path here only if the document is genuinely customer-facing copy.
        // Otherwise the curated FeatureInventoryService::seedFeatures() list is
        // the only source.
        'feature_docs' => array_values(array_filter(
            explode(',', (string) env('MARKETING_FEATURE_DOCS', ''))
        )),
    ],

    // Retention engine — locales for bilingual messages and default links.
    'retention' => [
        'locales' => env('RETENTION_LOCALES', 'fr,en'),
        'links' => [
            // No global FRONTEND_URL constant exists in this codebase — the app
            // is multi-tenant and mailables link to each tenant's own
            // Environment::primary_domain (see App\Mail\WelcomeToEnvironment).
            // www.getkursa.space is the PRODUCT LANDING PAGE (CSL-Sales-Website)
            // — it has no /dashboard. KURSA is multi-tenant: instructors and
            // learners each live on their own environment domain, resolved
            // per-target by RetentionLinks. These globals are the fallback and
            // the CTA for broadcast content (group tips / statuses / email
            // campaigns), which address users across ALL tenants at once.
            'panel' => env('RETENTION_LINK_PANEL', 'https://www.getkursa.space'),
            'site' => env('RETENTION_LINK_SITE', 'https://www.getkursa.space'),
            'support_whatsapp' => env('RETENTION_SUPPORT_WHATSAPP'),
        ],
    ],

];
