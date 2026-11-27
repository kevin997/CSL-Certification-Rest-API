<?php

namespace App\Services\Workflows;

use App\Models\Environment;
use App\Models\IssuedCertificate;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\Transaction;
use App\Models\User;
use App\Scopes\EnvironmentScope;

class WorkflowEventContext
{
    public function resolve(string $trigger, int $environmentId, int $subjectId): ?array
    {
        $environment = Environment::find($environmentId);
        if (! $environment || ! WorkflowDefinition::canRun($environment)) {
            return null;
        }

        $scope = fn (string $model) => $model::withoutGlobalScope(EnvironmentScope::class)->where('environment_id', $environmentId);
        $consent = null;
        $ids = [];
        if ($trigger === 'form_submitted') {
            $submission = $scope(SalesFormSubmission::class)->find($subjectId);
            if (! $submission || $submission->status === SalesFormSubmission::STATUS_CANCELLED) {
                return null;
            }
            $form = $scope(SalesForm::class)->find($submission->sales_form_id);
            if (! $form) {
                return null;
            }
            $event = 'form:'.$submission->id;
            $time = $submission->created_at;
            $userId = $submission->user_id;
            $reference = $form->title;
            $ids = [(int) $form->id];
            $consent = $submission;
        } elseif ($trigger === 'certificate_issued') {
            $certificate = $scope(IssuedCertificate::class)->find($subjectId);
            if (! $certificate || $certificate->status !== 'active' || $certificate->revoked_at) {
                return null;
            }
            $event = 'certificate:'.$certificate->id;
            $time = $certificate->created_at;
            $userId = $certificate->user_id;
            $reference = $certificate->certificate_number;
            $ids = [(int) $certificate->course_id];
        } elseif (in_array($trigger, ['order_placed', 'payment_confirmed'], true)) {
            $order = $scope(Order::class)->find($subjectId);
            if (! $order || in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED, Order::STATUS_PARTIALLY_REFUNDED], true)) {
                return null;
            }
            $time = $order->created_at;
            if ($trigger === 'payment_confirmed') {
                if ((float) $order->total_amount <= 0 || $order->type === Order::TYPE_ENROLLMENT_CODE) {
                    return null;
                }
                $transaction = Transaction::withoutGlobalScope(EnvironmentScope::class)->where('order_id', $order->id)
                    ->where(fn ($q) => $q->where('merchant_environment_id', $environmentId)
                        ->orWhere(fn ($q) => $q->whereNull('merchant_environment_id')->where('environment_id', $environmentId)))
                    ->whereIn('purpose', Transaction::REFUNDABLE_COURSE_PURPOSES)
                    ->where('amount', '>', 0)->where('status', Transaction::STATUS_COMPLETED)
                    ->whereNotNull('verified_at')->whereNotNull('paid_at')->whereNull('refunded_at')
                    ->whereNotNull('gateway_transaction_id')->where('gateway_transaction_id', '!=', '')
                    ->oldest('verified_at')->first();
                if (! $transaction) {
                    return null;
                }
                $time = $transaction->verified_at;
            }
            $event = $trigger.':'.$order->id;
            $userId = $order->user_id;
            $reference = $order->order_number;
            $ids = $scope(Product::class)->whereIn('id', $order->items()->pluck('product_id'))->pluck('id')
                ->map(fn ($id) => (int) $id)->all();
            if ($order->sales_form_submission_id) {
                $consent = $scope(SalesFormSubmission::class)->where('user_id', $userId)
                    ->where('status', '!=', SalesFormSubmission::STATUS_CANCELLED)
                    ->find($order->sales_form_submission_id);
            }
        } else {
            return null;
        }

        return [
            'environment' => $environment, 'time' => $time, 'event_key' => $event, 'event' => $trigger, 'filter_ids' => $ids,
            'subject_id' => $userId ? (int) $userId : null, 'consent' => $consent,
            'academy' => (string) $environment->name, 'reference' => (string) $reference,
            'name' => (string) ($consent?->name ?? ($userId ? User::find($userId)?->name : '') ?? ''),
        ];
    }
}
