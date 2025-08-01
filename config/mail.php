<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => 'smtp',

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('PHPMAILER_HOST', 'node127-eu.n0c.com'),
            'port' => env('PHPMAILER_PORT', 465),
            'username' => env('PHPMAILER_USERNAME', 'no.reply@cfpcsl.com'),
            'password' => env('PHPMAILER_PASSWORD'),
            'timeout' => env('PHPMAILER_TIMEOUT', 60),
            'encryption' => env('PHPMAILER_ENCRYPTION', 'ssl'),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],
        
        'phpmailer' => [
            'transport' => 'phpmailer',
            'host' => env('PHPMAILER_HOST', 'node127-eu.n0c.com'),
            'port' => env('PHPMAILER_PORT', 465),
            'username' => env('PHPMAILER_USERNAME', 'no.reply@cfpcsl.com'),
            'password' => env('PHPMAILER_PASSWORD', 'm?6Vx,mHrH'),
            'encryption' => env('PHPMAILER_ENCRYPTION', 'ssl'), // ssl, tls, or null
            'auth' => env('PHPMAILER_AUTH', true),
            'charset' => env('PHPMAILER_CHARSET', 'UTF-8'),
            'timeout' => env('PHPMAILER_TIMEOUT', 60),
            'debug' => env('PHPMAILER_DEBUG', 0), // 0 = off, 1 = client, 2 = client and server
        ],

        'mailjet' => [
            'transport' => 'mailjet',
            'key' => env('MAILJET_APIKEY'),
            'secret' => env('MAILJET_APISECRET'),
            'sandbox' => env('MAILJET_SANDBOX', false),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID', 'outbound'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('PHPMAILER_USERNAME', 'no.reply@cfpcsl.com'),
        'name' => env('MAIL_FROM_NAME', 'CSL'),
    ],

];
