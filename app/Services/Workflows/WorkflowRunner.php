<?php

namespace App\Services\Workflows;

use App\Mail\AutomationMail;
use App\Models\AcademyWorkflow;
use App\Models\EmailSuppression;
use App\Models\Enrollment;
use App\Models\EnvironmentUser;
use App\Models\MarketingAutomation;
use App\Models\MarketingConsent;
use App\Models\User;
use App\Models\UserNotification;
use App\Scopes\EnvironmentScope;
use App\Services\WachapNotificationService;
use App\Services\WhatsApp\AcademyWhatsApp;
use App\Support\Sales\SalesFormPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class WorkflowRunner
{
    public function __construct(
        private readonly WorkflowEventContext $events,
        private readonly AcademyWhatsApp $academyWhatsapp,
        private readonly WachapNotificationService $platformWhatsapp,
    ) {}

    public function run(string $trigger, int $environmentId, int $subjectId, ?int $observedAtUs = null): void
    {
        $query = AcademyWorkflow::where('environment_id', $environmentId)->where('active', true)
            ->where('published_trigger', $trigger)
            ->when($observedAtUs !== null, fn ($query) => $query->where('activated_at_us', '<=', $observedAtUs));
        if (! (clone $query)->exists()) {
            return;
        }

        $context = $this->events->resolve($trigger, $environmentId, $subjectId);
        if (! $context || ! $context['time'] || $context['time']->lt(now()->subDays(2))) {
            return;
        }

        $workflows = $query->where('published_at', '<=', $context['time'])->orderBy('id')->get();

        foreach ($workflows as $workflow) {
            foreach ($workflow->published['actions'] as $action) {
                $this->process($workflow, $action, $context, $subjectId);
            }
        }
    }

    private function process(AcademyWorkflow $workflow, array $action, array $context, int $subjectId): void
    {
        $channel = $action['channel'];
        $key = [
            'academy_workflow_id' => $workflow->id, 'event_key' => $context['event_key'], 'channel' => $channel,
        ];
        if (! DB::table('academy_workflow_runs')->insertOrIgnore($key + [
            'state' => 'processing', 'revision' => $workflow->revision,
            'created_at' => now(), 'updated_at' => now(),
        ])) {
            return;
        }

        $finish = fn (string $state, ?string $reason = null) => DB::table('academy_workflow_runs')->where($key)->update([
            'state' => $state, 'reason' => $reason, 'updated_at' => now(),
        ]);

        $mayHaveSent = false;
        try {
            $definition = $workflow->published;
            if ($definition['filter_id'] !== null && ! in_array($definition['filter_id'], $context['filter_ids'], true)) {
                $finish('skipped', 'condition_not_met');

                return;
            }

            $fresh = fn () => $this->eligible($workflow, $action, $subjectId);
            [$reason, $recipient, $coordinates, $event] = $fresh();
            if ($reason) {
                $finish('skipped', $reason);

                return;
            }
            if (! DB::table('academy_workflow_deliveries')->insertOrIgnore([
                'environment_id' => $workflow->environment_id, 'event_key' => $context['event_key'],
                'recipient_id' => $recipient->id, 'channel' => $channel, 'created_at' => now(),
            ])) {
                $finish('skipped', 'duplicate');

                return;
            }

            $title = mb_substr(str_replace(["\r", "\n"], ' ', WorkflowDefinition::render($action['title'], $event)), 0, 150);
            $message = mb_substr(WorkflowDefinition::render($action['message'], $event), 0, 3000);
            $stillEligible = function () use ($fresh, $recipient, $coordinates): bool {
                [$why, $current, $address] = $fresh();

                return $why === null && $current?->id === $recipient->id && $address === $coordinates;
            };

            if (! $stillEligible()) {
                $finish('skipped', 'eligibility_changed');

                return;
            }
            if ($channel === 'in_app') {
                UserNotification::create([
                    'environment_id' => $workflow->environment_id, 'user_id' => $recipient->id,
                    'type' => 'academy_workflow', 'title' => $title, 'message' => $message,
                ]);
            } elseif ($channel === 'email') {
                $mayHaveSent = true;
                // Already on a queue worker. Do not enqueue a second mail job
                // that would send after consent or workflow state changes.
                $sent = (new AutomationMail($title, $message, $event['environment']))
                    ->to($coordinates)->send(Mail::mailer());
                if (! $sent) {
                    $finish('skipped', 'email_not_sent');

                    return;
                }
            } elseif ($channel === 'whatsapp') {
                $sender = $this->academyWhatsapp->senderFor($event['environment']);
                $mayHaveSent = true;
                $response = $sender
                    ? $this->academyWhatsapp->send($event['environment'], $sender, $coordinates, $message, $stillEligible)
                    : $this->platformWhatsapp->sendVerifiedPaymentReminder($coordinates, $message, $stillEligible);
                if (! $response?->successful()) {
                    $finish('skipped', 'recipient_or_eligibility');

                    return;
                }
            }

            $finish($channel === 'in_app' ? 'delivered' : 'accepted');
        } catch (Throwable) {
            // A timeout after dispatch can mean the provider received the
            // message. Never release its reservation or retry it blindly.
            $finish($mayHaveSent ? 'unconfirmed' : 'failed', $mayHaveSent ? 'provider_unconfirmed' : 'execution_error');
        }
    }

    /** @return array{0:?string, 1:?User, 2:?string, 3:?array} */
    private function eligible(AcademyWorkflow $workflow, array $action, int $subjectId): array
    {
        $skip = fn (string $why): array => [$why, null, null, null];
        $current = AcademyWorkflow::where('environment_id', $workflow->environment_id)->find($workflow->id);
        if (! $current?->active || $current->revision !== $workflow->revision) {
            return $skip('workflow_changed');
        }
        $event = $this->events->resolve($workflow->published_trigger, $workflow->environment_id, $subjectId);
        $filter = $workflow->published['filter_id'];
        if (! $event || ($filter !== null && ! in_array($filter, $event['filter_ids'], true))) {
            return $skip('event_unavailable');
        }
        $channel = $action['channel'];
        $owner = $action['recipient'] === 'owner';
        $recipientId = $owner ? $event['environment']->owner_id : $event['subject_id'];
        $recipient = $recipientId ? User::find($recipientId) : null;
        if (! $recipient) {
            return $skip('recipient_unavailable');
        }
        if ($channel === 'in_app') {
            if (! $owner && ! EnvironmentUser::where('environment_id', $workflow->environment_id)->where('user_id', $recipientId)->exists() &&
                ! Enrollment::withoutGlobalScope(EnvironmentScope::class)->where('environment_id', $workflow->environment_id)
                    ->where('user_id', $recipientId)->whereIn('status', [Enrollment::STATUS_ENROLLED, Enrollment::STATUS_IN_PROGRESS, Enrollment::STATUS_COMPLETED, 'active'])->exists()) {
                return $skip('recipient_not_member');
            }

            return [null, $recipient, null, $event];
        }
        if (! WorkflowDefinition::channelAvailable($channel, $this->academyWhatsapp)) {
            return $skip('channel_unavailable');
        }
        $default = MarketingAutomation::enabledFor($workflow->environment_id, $workflow->published_trigger);
        if ($default?->usesChannel($channel) || ($channel === 'email' && ! $owner && $workflow->published_trigger === 'certificate_issued')) {
            return $skip('system_workflow_overlap');
        }
        if (! $owner && (! $event['consent'] || ! MarketingConsent::isGranted($event['consent'], $channel))) {
            return $skip('consent_required');
        }
        if ($channel === 'email') {
            $address = trim((string) ($owner ? $recipient->email : $event['consent']->email));
            if (! filter_var($address, FILTER_VALIDATE_EMAIL) || EmailSuppression::isSuppressed($address)) {
                return $skip('address_unavailable');
            }
        } else {
            $address = SalesFormPhone::normalize($owner ? $recipient->whatsapp_number : $event['consent']->phone);
            if (! $address) {
                return $skip('address_unavailable');
            }
            $address = substr($address, 1);
        }

        return [null, $recipient, $address, $event];
    }
}
