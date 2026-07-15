<?php

namespace App\Services;

use App\Models\RetentionMessage;
use App\Support\Retention\RetentionLinks;
use App\Support\Retention\RetentionScenario;
use App\Support\Retention\RetentionScenarioRegistry;
use Illuminate\Support\Collection;

/**
 * Turns the scenario catalogue into a concrete, de-duplicated send list for
 * today's run. Rules enforced here:
 *   - one message per recipient per run (the highest-priority match wins);
 *   - per-scenario cooldown (don't repeat the same nudge within N days);
 *   - global cooldown (don't message the same person more than once every
 *     GLOBAL_COOLDOWN_DAYS, across all scenarios) — anti-fatigue.
 *
 * Ported from shopikat's RetentionCampaignService, minus the coupon-minting
 * step (KURSA has no discount-code system).
 */
class RetentionCampaignService
{
    /** Don't message the same recipient more than once within this many days. */
    public const GLOBAL_COOLDOWN_DAYS = 2;

    public function __construct(
        private readonly RetentionScenarioRegistry $registry,
        private readonly RetentionLinks $links,
    ) {}

    /**
     * @return array<int, array{recipient_type:string, recipient_id:string, scenario_key:string, phone:?string, email:?string, message:string, push_user_id:?int, environment_id:?int}>
     */
    public function buildSendList(): array
    {
        /** @var Collection<int, RetentionScenario> $scenarios */
        $scenarios = collect($this->registry->all())->sortByDesc->priority;

        $windowDays = max(
            self::GLOBAL_COOLDOWN_DAYS,
            (int) ($scenarios->max('cooldownDays') ?? self::GLOBAL_COOLDOWN_DAYS),
        );

        // Preload recent history once, then evaluate cooldowns in memory.
        $recentByRecipient = RetentionMessage::query()
            ->where('status', RetentionMessage::STATUS_SENT)
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->get(['recipient_type', 'recipient_id', 'scenario_key', 'created_at'])
            ->groupBy(fn (RetentionMessage $m) => $m->recipient_type.':'.$m->recipient_id);

        $assigned = [];   // recipientKey => true (already got a message this run)
        $sendList = [];

        foreach ($scenarios as $scenario) {
            foreach ($scenario->targets() as $target) {
                $key = $target->recipientType.':'.$target->recipientId;

                if (isset($assigned[$key])) {
                    continue;
                }

                $history = $recentByRecipient->get($key, collect());

                // Global anti-fatigue cooldown.
                if ($history->contains(fn ($h) => $h->created_at->gt(now()->subDays(self::GLOBAL_COOLDOWN_DAYS)))) {
                    continue;
                }

                // Per-scenario cooldown.
                if ($history->contains(fn ($h) => $h->scenario_key === $scenario->key
                    && $h->created_at->gt(now()->subDays($scenario->cooldownDays)))) {
                    continue;
                }

                $assigned[$key] = true;

                $link = $this->links->forScenario($scenario->key, $target->context);
                $message = $scenario->render($target)."\n\n👉 ".$link;

                $sendList[] = [
                    'recipient_type' => $target->recipientType,
                    'recipient_id' => $target->recipientId,
                    'scenario_key' => $scenario->key,
                    'phone' => $target->phone,
                    'email' => $target->email,
                    'message' => $message,
                    'push_user_id' => $target->pushUserId,
                    'environment_id' => isset($target->context['environment_id']) ? (int) $target->context['environment_id'] : null,
                ];
            }
        }

        return $sendList;
    }
}
