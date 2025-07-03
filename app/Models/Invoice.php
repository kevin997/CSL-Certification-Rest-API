<?php

// app/Models/Invoice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment;

    protected $fillable = [
        'environment_id',
        'invoice_number',
        'month',
        'total_fee_amount',
        'currency',
        'status',
        'due_date',
        'payment_link',
        'payment_gateway',
        'paid_at',
        'transaction_count',
        'metadata',
    ];

    protected $casts = [
        'month' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function environment()
    {
        return $this->belongsTo(Environment::class);
    }
}