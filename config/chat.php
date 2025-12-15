<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chat Certificate Configuration
    |--------------------------------------------------------------------------
    |
    | These values determine the minimum requirements for users to earn
    | participation certificates through chat engagement.
    |
    */
    'certificate' => [
        'min_messages' => env('CHAT_CERT_MIN_MESSAGES', 10),
        'min_active_days' => env('CHAT_CERT_MIN_ACTIVE_DAYS', 3),
        'min_engagement_score' => env('CHAT_CERT_MIN_ENGAGEMENT_SCORE', 70),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for chat analytics processing and caching.
    |
    */
    'analytics' => [
        'cache_duration' => env('CHAT_ANALYTICS_CACHE_DURATION', 3600), // 1 hour in seconds
        'batch_size' => env('CHAT_ANALYTICS_BATCH_SIZE', 100),
        'retention_days' => env('CHAT_ANALYTICS_RETENTION_DAYS', 365),
        'max_report_days' => env('CHAT_ANALYTICS_MAX_REPORT_DAYS', 365),
        'enable_real_time_processing' => env('CHAT_ANALYTICS_REAL_TIME', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Main API Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to the main API to fetch chat data
    | and enrollment information.
    |
    */
    'main_api' => [
        'url' => env('MAIN_API_URL', 'https://certification.csl-certification.com'),
        'token' => env('MAIN_API_TOKEN'),
        'timeout' => env('MAIN_API_TIMEOUT', 30),
        'retry_attempts' => env('MAIN_API_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('MAIN_API_RETRY_DELAY', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificate Service Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for the certificate generation microservice.
    |
    */
    'certificate_service' => [
        'url' => env('CERTIFICATE_SERVICE_URL'),
        'token' => env('CERTIFICATE_SERVICE_TOKEN'),
        'timeout' => env('CERTIFICATE_SERVICE_TIMEOUT', 60),
        'default_template' => 'discussion_participation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Engagement Score Calculation
    |--------------------------------------------------------------------------
    |
    | Weights for different factors in engagement score calculation.
    | All weights should sum to 100 for proper percentage calculation.
    |
    */
    'engagement_weights' => [
        'consistency' => env('CHAT_ENGAGEMENT_CONSISTENCY_WEIGHT', 40), // Active days consistency
        'volume' => env('CHAT_ENGAGEMENT_VOLUME_WEIGHT', 30),          // Message volume
        'quality' => env('CHAT_ENGAGEMENT_QUALITY_WEIGHT', 30),        // Message quality
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings to optimize performance for large datasets.
    |
    */
    'performance' => [
        'enable_query_optimization' => env('CHAT_ANALYTICS_OPTIMIZE_QUERIES', true),
        'use_database_views' => env('CHAT_ANALYTICS_USE_VIEWS', false),
        'parallel_processing' => env('CHAT_ANALYTICS_PARALLEL_PROCESSING', false),
        'chunk_size' => env('CHAT_ANALYTICS_CHUNK_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Quality Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for data validation and quality assurance.
    |
    */
    'data_quality' => [
        'validate_message_content' => env('CHAT_ANALYTICS_VALIDATE_CONTENT', true),
        'min_message_length' => env('CHAT_ANALYTICS_MIN_MESSAGE_LENGTH', 1),
        'max_message_length' => env('CHAT_ANALYTICS_MAX_MESSAGE_LENGTH', 10000),
        'exclude_system_messages' => env('CHAT_ANALYTICS_EXCLUDE_SYSTEM', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Settings for notifications related to certificate generation and
    | analytics processing.
    |
    */
    'notifications' => [
        'certificate_generated' => env('CHAT_NOTIFY_CERTIFICATE_GENERATED', true),
        'analytics_processed' => env('CHAT_NOTIFY_ANALYTICS_PROCESSED', false),
        'error_reporting' => env('CHAT_NOTIFY_ERRORS', true),
        'channels' => [
            'email' => env('CHAT_NOTIFY_EMAIL', true),
            'slack' => env('CHAT_NOTIFY_SLACK', false),
            'database' => env('CHAT_NOTIFY_DATABASE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy and Security
    |--------------------------------------------------------------------------
    |
    | Privacy and security settings for chat analytics.
    |
    */
    'privacy' => [
        'anonymize_user_data' => env('CHAT_ANALYTICS_ANONYMIZE_USERS', false),
        'hash_user_ids' => env('CHAT_ANALYTICS_HASH_USER_IDS', false),
        'retention_policy' => env('CHAT_ANALYTICS_RETENTION_POLICY', 'standard'), // standard, minimal, extended
        'gdpr_compliance' => env('CHAT_ANALYTICS_GDPR_COMPLIANCE', true),
        'data_export_enabled' => env('CHAT_ANALYTICS_DATA_EXPORT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for analytics reports.
    |
    */
    'reporting' => [
        'default_period_days' => env('CHAT_ANALYTICS_DEFAULT_PERIOD', 30),
        'max_participants_in_report' => env('CHAT_ANALYTICS_MAX_PARTICIPANTS', 1000),
        'include_inactive_users' => env('CHAT_ANALYTICS_INCLUDE_INACTIVE', false),
        'export_formats' => ['json', 'csv', 'pdf'],
        'timezone' => env('CHAT_ANALYTICS_TIMEZONE', 'UTC'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific features in the analytics system.
    |
    */
    'features' => [
        'advanced_sentiment_analysis' => env('CHAT_FEATURE_SENTIMENT_ANALYSIS', false),
        'thread_analysis' => env('CHAT_FEATURE_THREAD_ANALYSIS', true),
        'peak_time_analysis' => env('CHAT_FEATURE_PEAK_TIME_ANALYSIS', true),
        'instructor_insights' => env('CHAT_FEATURE_INSTRUCTOR_INSIGHTS', true),
        'predictive_analytics' => env('CHAT_FEATURE_PREDICTIVE_ANALYTICS', false),
        'automated_reporting' => env('CHAT_FEATURE_AUTOMATED_REPORTING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat Message Archival Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for automated chat message archival system.
    | These settings control how old chat messages are archived to S3 storage
    | and how the archival process behaves.
    |
    */

    'archival' => [

        /*
        |--------------------------------------------------------------------------
        | Archive Threshold Days
        |--------------------------------------------------------------------------
        |
        | Messages older than this number of days will be eligible for archival.
        | Default is 90 days. This can be overridden per-course or via command options.
        |
        */

        'threshold_days' => env('CHAT_ARCHIVAL_THRESHOLD_DAYS', 90),

        /*
        |--------------------------------------------------------------------------
        | Batch Size
        |--------------------------------------------------------------------------
        |
        | Number of messages to process in each batch during archival.
        | Larger batches are more efficient but use more memory.
        | Recommended range: 500-2000 messages per batch.
        |
        */

        'batch_size' => env('CHAT_ARCHIVAL_BATCH_SIZE', 1000),

        /*
        |--------------------------------------------------------------------------
        | Archive File Format
        |--------------------------------------------------------------------------
        |
        | Configuration for archive file format and compression settings.
        |
        */

        'file_format' => [
            'extension' => 'json', // Archive file extension
            'compression' => env('CHAT_ARCHIVAL_COMPRESSION', true), // Enable gzip compression
            'include_metadata' => true, // Include message metadata in archives
            'verify_integrity' => true, // Generate and verify checksums
        ],

        /*
        |--------------------------------------------------------------------------
        | S3 Storage Configuration
        |--------------------------------------------------------------------------
        |
        | S3 bucket and path configuration for archived messages.
        |
        */

        's3' => [
            'bucket' => env('CHAT_ARCHIVAL_S3_BUCKET', env('AWS_BUCKET')),
            'path_prefix' => env('CHAT_ARCHIVAL_S3_PATH', 'chat-archives'),
            'storage_class' => env('CHAT_ARCHIVAL_S3_STORAGE_CLASS', 'STANDARD_IA'), // STANDARD, STANDARD_IA, GLACIER
            'server_side_encryption' => env('CHAT_ARCHIVAL_S3_ENCRYPTION', 'AES256'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Performance Settings
        |--------------------------------------------------------------------------
        |
        | Settings to optimize archival performance for large datasets.
        |
        */

        'performance' => [
            'memory_limit' => env('CHAT_ARCHIVAL_MEMORY_LIMIT', '512M'),
            'timeout' => env('CHAT_ARCHIVAL_TIMEOUT', 3600), // 1 hour in seconds
            'concurrent_uploads' => env('CHAT_ARCHIVAL_CONCURRENT_UPLOADS', 3),
            'retry_attempts' => env('CHAT_ARCHIVAL_RETRY_ATTEMPTS', 3),
        ],

        /*
        |--------------------------------------------------------------------------
        | Cleanup Settings
        |--------------------------------------------------------------------------
        |
        | Settings for cleaning up old archival jobs and temporary files.
        |
        */

        'cleanup' => [
            'keep_job_records_days' => env('CHAT_ARCHIVAL_KEEP_JOBS_DAYS', 365),
            'temp_file_retention_hours' => env('CHAT_ARCHIVAL_TEMP_RETENTION_HOURS', 24),
            'failed_job_retention_days' => env('CHAT_ARCHIVAL_FAILED_JOB_RETENTION_DAYS', 90),
        ],

        /*
        |--------------------------------------------------------------------------
        | Admin Email
        |--------------------------------------------------------------------------
        |
        | Email address for archival notifications and error reporting.
        |
        */

        'admin_email' => env('CHAT_ARCHIVAL_ADMIN_EMAIL', env('MAIL_FROM_ADDRESS')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for chat message search functionality.
    | Controls search indexing, performance, and search behavior.
    |
    */

    'search' => [

        /*
        |--------------------------------------------------------------------------
        | Search Index Settings
        |--------------------------------------------------------------------------
        |
        | Configuration for full-text search index management.
        |
        */

        'indexing' => [
            'batch_size' => env('CHAT_SEARCH_INDEX_BATCH_SIZE', 1000),
            'auto_index_new_messages' => env('CHAT_SEARCH_AUTO_INDEX', true),
            'index_archived_messages' => env('CHAT_SEARCH_INDEX_ARCHIVED', true),
            'min_content_length' => env('CHAT_SEARCH_MIN_CONTENT_LENGTH', 3),
            'max_content_length' => env('CHAT_SEARCH_MAX_CONTENT_LENGTH', 10000),
        ],

        /*
        |--------------------------------------------------------------------------
        | Search Performance
        |--------------------------------------------------------------------------
        |
        | Settings to optimize search performance and user experience.
        |
        */

        'performance' => [
            'cache_ttl_seconds' => env('CHAT_SEARCH_CACHE_TTL', 300), // 5 minutes
            'max_results' => env('CHAT_SEARCH_MAX_RESULTS', 500),
            'default_limit' => env('CHAT_SEARCH_DEFAULT_LIMIT', 50),
            'query_timeout' => env('CHAT_SEARCH_QUERY_TIMEOUT', 10), // seconds
            'enable_query_cache' => env('CHAT_SEARCH_ENABLE_CACHE', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Search Features
        |--------------------------------------------------------------------------
        |
        | Enable or disable various search features and behaviors.
        |
        */

        'features' => [
            'enable_suggestions' => env('CHAT_SEARCH_ENABLE_SUGGESTIONS', true),
            'enable_analytics' => env('CHAT_SEARCH_ENABLE_ANALYTICS', true),
            'enable_relevance_scoring' => env('CHAT_SEARCH_ENABLE_SCORING', true),
            'include_user_context' => env('CHAT_SEARCH_USER_CONTEXT', true),
            'highlight_matches' => env('CHAT_SEARCH_HIGHLIGHT', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Search Query Settings
        |--------------------------------------------------------------------------
        |
        | Configuration for search query processing and validation.
        |
        */

        'query' => [
            'min_length' => env('CHAT_SEARCH_MIN_QUERY_LENGTH', 2),
            'max_length' => env('CHAT_SEARCH_MAX_QUERY_LENGTH', 255),
            'enable_boolean_mode' => env('CHAT_SEARCH_BOOLEAN_MODE', true),
            'enable_wildcard_search' => env('CHAT_SEARCH_WILDCARD', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Index Maintenance
        |--------------------------------------------------------------------------
        |
        | Settings for maintaining search index health and performance.
        |
        */

        'maintenance' => [
            'cleanup_days' => env('CHAT_SEARCH_CLEANUP_DAYS', 365),
            'rebuild_threshold_days' => env('CHAT_SEARCH_REBUILD_THRESHOLD', 30),
            'optimize_frequency_days' => env('CHAT_SEARCH_OPTIMIZE_DAYS', 7),
        ],

        /*
        |--------------------------------------------------------------------------
        | Search Result Formatting
        |--------------------------------------------------------------------------
        |
        | Configuration for how search results are formatted and presented.
        |
        */

        'results' => [
            'snippet_length' => env('CHAT_SEARCH_SNIPPET_LENGTH', 150),
            'highlight_tags' => [
                'start' => '<mark>',
                'end' => '</mark>',
            ],
            'include_context' => env('CHAT_SEARCH_INCLUDE_CONTEXT', true),
            'context_window' => env('CHAT_SEARCH_CONTEXT_WINDOW', 50), // characters before/after match
        ],
    ],
];