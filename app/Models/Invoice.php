<?php

// app/Models/Invoice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\InstructorCommission;

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
        'pdf_path',
    ];

    protected $casts = [
        'month' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'metadata' => 'array',
        'total_fee_amount' => 'decimal:2',
    ];

    public function environment()
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get the commission records linked to this invoice.
     */
    public function commissions()
    {
        return $this->hasMany(InstructorCommission::class);
    }
}