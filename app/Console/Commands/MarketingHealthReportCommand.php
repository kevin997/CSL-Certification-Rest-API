<?php

namespace App\Console\Commands;

use App\Models\MarketingMessage;
use App\Models\RetentionMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Weekly self-report of the KURSA marketing & retention engine: content pool
 * levels, what was generated/sent per channel, retention sends per scenario,
 * and assistant usage — emailed to the operator so the fully-automated engine
 * stays observable without anyone tailing logs.
 */
class MarketingHealthReportCommand extends Command
{
    protected $signature = 'kursa:marketing-health-report {--days=7} {--email=}';

    protected $description = 'Email a health summary of the marketing/retention engine (pools, sends, retention, assistant usage)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);
        $to = $this->option('email') ?: config('mail.operator_address', 'kevinliboire@gmail.com');

        $lines = [];
        $lines[] = 'KURSA MARKETING & RETENTION — HEALTH REPORT ('.$days.'j)';
        $lines[] = 'Généré le '.now()->timezone('Africa/Douala')->format('d/m/Y H:i').' (Douala)';
        $lines[] = str_repeat('=', 60);

        // Content pool per channel: pending now / generated / sent in window.
        $lines[] = '';
        $lines[] = 'POOL DE CONTENU (IA)';
        foreach ([
            MarketingMessage::CHANNEL_GROUP_TIP => 'Astuces groupe WhatsApp',
            MarketingMessage::CHANNEL_STATUS => 'Statuts WhatsApp',
            MarketingMessage::CHANNEL_EMAIL => 'Campagnes email',
        ] as $channel => $label) {
            $pending = MarketingMessage::where('channel', $channel)->where('status', MarketingMessage::STATUS_PENDING)->count();
            $generated = MarketingMessage::where('channel', $channel)->where('created_at', '>=', $since)->count();
            $sent = MarketingMessage::where('channel', $channel)->where('status', MarketingMessage::STATUS_SENT)->where('sent_at', '>=', $since)->count();
            $flag = $pending < 3 ? '  ⚠ POOL BAS' : '';
            $lines[] = sprintf('  %-28s en attente: %-3d générés: %-3d envoyés: %d%s', $label, $pending, $generated, $sent, $flag);
        }

        // Retention sends per scenario/status.
        $lines[] = '';
        $lines[] = 'RÉTENTION ('.$days.'j)';
        $rows = RetentionMessage::where('created_at', '>=', $since)
            ->select('scenario_key', 'status', DB::raw('count(*) as n'))
            ->groupBy('scenario_key', 'status')
            ->orderBy('scenario_key')
            ->get();
        if ($rows->isEmpty()) {
            $lines[] = '  (aucun envoi)';
        }
        foreach ($rows->groupBy('scenario_key') as $scenario => $group) {
            $parts = $group->map(fn ($r) => $r->status.': '.$r->n)->implode(', ');
            $lines[] = sprintf('  %-28s %s', $scenario, $parts);
        }
        $failed = RetentionMessage::where('created_at', '>=', $since)->where('status', RetentionMessage::STATUS_FAILED)->count();
        if ($failed > 0) {
            $lines[] = '  ⚠ '.$failed.' échec(s) — voir storage/logs.';
        }

        // Assistant usage (SDK conversation tables).
        $lines[] = '';
        $lines[] = 'ASSISTANT INSTRUCTEUR ('.$days.'j)';
        try {
            $convos = DB::table('agent_conversations')->where('created_at', '>=', $since)->count();
            $msgs = DB::table('agent_conversation_messages')->where('created_at', '>=', $since)->count();
            $lines[] = '  Conversations: '.$convos.'  ·  Messages: '.$msgs;
        } catch (\Throwable) {
            $lines[] = '  (tables de conversation indisponibles)';
        }

        // Knowledge base freshness.
        $lines[] = '';
        $chunks = DB::table('content_chunks')->count();
        $embedded = DB::table('content_chunks')->whereNotNull('embedding')->count();
        $lastIndexed = DB::table('content_chunks')->max('created_at');
        $lines[] = 'BASE DE CONNAISSANCES: '.$chunks.' extraits ('.$embedded.' vectorisés), dernier ajout: '.($lastIndexed ?: 'jamais');

        $body = implode("\n", $lines);
        $this->line($body);

        Mail::raw($body, function ($message) use ($to, $days) {
            $message->to($to)->subject('[KURSA] Rapport marketing & rétention — '.$days.'j');
        });
        $this->info('Report emailed to '.$to);

        return self::SUCCESS;
    }
}
