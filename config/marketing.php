<?php

/*
|--------------------------------------------------------------------------
| Marketing & retention channels
|--------------------------------------------------------------------------
|
| Transactional email (welcome, password reset, order confirmation, receipts,
| certificates, billing reminders) is always sent. The flags below only gate
| *marketing/retention* messaging so the transactional mailbox cannot be
| exhausted by campaigns, digests, automations or retention nudges.
|
| WhatsApp remains the marketing/retention channel. Set the env vars to true
| to re-enable email/push marketing later.
|
*/

return [
    'email_enabled' => env('MARKETING_EMAIL_ENABLED', false),
    'push_enabled' => env('MARKETING_PUSH_ENABLED', false),
];
