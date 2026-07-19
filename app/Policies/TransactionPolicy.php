<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * KURSA licensing transition (Phase 3, doc §9.8): transaction reads are gated to
 * the environment owner / admin, and status mutation is admin-only. Settlement
 * transitions (completed/refunded) are never client-driven — those are produced
 * by verified gateway events (WebhookProcessor) and the refund flow, and are
 * additionally blocked in TransactionController::updateStatus.
 */
class TransactionPolicy
{
    use HandlesAuthorization;

    /**
     * Global admins / super admins bypass per-record checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || (bool) ($user->is_admin ?? false)) {
            return true;
        }

        return null;
    }

    /**
     * List transactions: environment owners only (query is environment-scoped);
     * learners are denied. Admins pass via before().
     */
    public function viewAny(User $user): bool
    {
        return $user->environments()->exists();
    }

    /**
     * Read a single transaction: the owner of the transaction's environment only.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        $environment = $transaction->environment;

        return $environment !== null
            && (int) $environment->owner_id === (int) $user->id;
    }

    /**
     * Mutate transaction status: admins only (handled by before(); non-admins
     * are denied here). Non-settlement transitions still route through this.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        return false;
    }
}
