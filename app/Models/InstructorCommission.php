<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instructor Commission Model
 *
 * Tracks commission records for instructors on transactions
 * processed through centralized payment gateways.
 */
class InstructorCommission extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'instructor_commissions';

    /**
     * Commission statuses. KURSA licensing transition (Phase 5, doc §9.9):
     * `reversed` (refund reversed a not-yet-paid liability) and `on_hold`
     * (settlement frozen while a chargeback/dispute is open) were added.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';
    const STATUS_DISPUTED = 'disputed';
    const STATUS_REVERSED = 'reversed';
    const STATUS_ON_HOLD = 'on_hold';

    /**
     * Statuses whose payout liability is still outstanding (safe to reverse /
     * hold without clawing back money already paid to the instructor).
     *
     * @var array<int, string>
     */
    const UNSETTLED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_ON_HOLD,
        self::STATUS_DISPUTED,
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'environment_id',
        'transaction_id',
        'order_id',
        'gross_amount',
        'platform_fee_rate',
        'platform_fee_amount',
        'instructor_payout_amount',
        'currency',
        'status',
        'paid_at',
        'payment_reference',
        'withdrawal_request_id',
        'invoice_id',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'gross_amount' => 'decimal:2',
        'platform_fee_rate' => 'decimal:4',
        'platform_fee_amount' => 'decimal:2',
        'instructor_payout_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the environment that owns the commission.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get the transaction associated with this commission.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the order associated with this commission.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the withdrawal request associated with this commission.
     */
    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class);
    }

    /**
     * Get the invoice associated with this commission.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
