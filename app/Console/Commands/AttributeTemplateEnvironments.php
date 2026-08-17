<?php

namespace App\Console\Commands;

use App\Models\CertificateTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assigns an owning environment to certificate templates that predate the
 * environment_id column.
 *
 * Ownership is derived from use, not from created_by: the templates in
 * production were created by a user attached to no environment, while the
 * courses that reference them are environment-scoped. A template used by
 * exactly one environment is attributed to it; anything ambiguous is reported
 * and left alone for a human to decide.
 */
class AttributeTemplateEnvironments extends Command
{
    protected $signature = 'certificates:attribute-template-environments
                            {--apply : Write the attributions. Without this, only report.}';

    protected $description = 'Attribute unowned certificate templates to the environment that uses them';

    public function handle(): int
    {
        $unowned = CertificateTemplate::query()->whereNull('environment_id')->get();

        if ($unowned->isEmpty()) {
            $this->info('No unowned templates.');

            return self::SUCCESS;
        }

        $usage = $this->usageByTemplate();
        $attributed = $ambiguous = $orphaned = 0;

        foreach ($unowned as $template) {
            $environments = $usage->get($template->getKey(), collect());

            if ($environments->isEmpty()) {
                $orphaned++;
                $this->warn("template {$template->id} ({$template->name}): no course usage, leaving unowned");

                continue;
            }

            if ($environments->count() > 1) {
                $ambiguous++;
                $this->warn(
                    "template {$template->id} ({$template->name}): used by environments "
                    .$environments->implode(', ').', leaving unowned'
                );

                continue;
            }

            $environmentId = (int) $environments->first();
            $this->line("template {$template->id} ({$template->name}) -> environment {$environmentId}");

            if ($this->option('apply')) {
                $template->update(['environment_id' => $environmentId]);
            }

            $attributed++;
        }

        $this->newLine();
        $this->info("attributed {$attributed}, ambiguous {$ambiguous}, no usage {$orphaned}");

        if (! $this->option('apply')) {
            $this->comment('Dry run: nothing written. Re-run with --apply.');
        }

        if ($ambiguous > 0 || $orphaned > 0) {
            $this->comment(
                'Templates left unowned stay hidden from every tenant. Set '
                .'environment_id by hand for the ones that should remain in use.'
            );
        }

        return self::SUCCESS;
    }

    /**
     * Environments each template is reachable from, via the courses whose
     * activities carry a certificate content pointing at it.
     *
     * @return Collection<int, Collection<int, int>>
     */
    private function usageByTemplate()
    {
        // Kept as pairs rather than plucked into a map: plucking would key by
        // template id, so a template used by two environments would silently
        // collapse to one and defeat the ambiguity check below.
        return DB::table('certificate_contents as cc')
            ->join('course_section_items as csi', 'csi.activity_id', '=', 'cc.activity_id')
            ->join('course_sections as cs', 'cs.id', '=', 'csi.course_section_id')
            ->join('courses as c', 'c.id', '=', 'cs.course_id')
            ->whereNotNull('cc.certificate_template_id')
            ->whereNotNull('c.environment_id')
            ->distinct()
            ->select('cc.certificate_template_id as template_id', 'c.environment_id as environment_id')
            ->get()
            ->groupBy('template_id')
            ->map(fn ($rows) => $rows->pluck('environment_id')->unique()->values());
    }
}
