<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignFunder extends Model
{
    use HasFactory;

    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_SUCCESS = 'success';
    public const PAYMENT_STATUS_FAILURE = 'failure';

    protected $fillable = [
        'full_name',
        'email',
        'whatsapp_number',
        'locale',
        'tier_id',
        'tier_name',
        'amount_xaf',
        'currency',
        'note',
        'terms_accepted_at',
        'source',
        'payment_provider',
        'payment_status',
        'tara_payment_id',
        'tara_collection_id',
        'tara_transaction_code',
        'tara_mobile_operator',
        'tara_phone_number',
        'tara_customer_name',
        'paid_at',
        'failed_at',
        'payment_links',
        'meta',
        'last_webhook_payload',
    ];

    protected $casts = [
        'terms_accepted_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'payment_links' => 'array',
        'meta' => 'array',
        'last_webhook_payload' => 'array',
    ];
}
