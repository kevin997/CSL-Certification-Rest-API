<?php

namespace App\Console\Commands;

use App\Mail\InstructorWeeklyDigest;
use App\Models\ActivityCompletion;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\EventContent;
use App\Models\Activity;
use App\Models\IssuedCertificate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInstructorWeeklyDigest extends Command
{
    protected $signature = 'engagement:instructor-digest {--dry-run : Preview stats without sending emails}';
    protected $description = 'Send weekly digest emails to environment owners/instructors';

    public function handle(): int
    {
        $this->info('Starting instructor weekly digest...');

        $environments = Environment::where('is_active', true)
            ->whereNotNull('owner_id')
            ->with('owner')
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($environments as $environment) {
            if (!$environment->owner || !$environment->owner->email) {
                $this->warn("Skipping environment #{$environment->id} — no owner email.");
                $skipped++;
                continue;
            }

            try {
                $stats = $this->gatherStats($environment->id);

                // Skip if there's zero activity this week
                if ($this->isInactive($stats)) {
                    $this->line("Skipping {$environment->name} — no activity this week.");
                    $skipped++;
                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->info("DRY-RUN: {$environment->name} → {$environment->owner->email}");
                    $this->table(
                        ['Metric', 'Value'],
                        collect($stats)->except(['top_course', 'upcoming_events'])->map(fn($v, $k) => [$k, $v])->values()->toArray()
                    );
                } else {
                    Mail::to($environment->owner->email)
                        ->send(new InstructorWeeklyDigest($environment->owner, $environment, $stats));
                    $this->info("Sent to {$environment->owner->email} ({$environment->name})");
                }

                $sent++;
            } catch (\Exception $e) {
                Log::error("Instructor digest failed for environment #{$environment->id}: {$e->getMessage()}");
                $this->error("Failed for {$environment->name}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Sent: {$sent}, Skipped: {$skipped}");
        return self::SUCCESS;
    }

    private function gatherStats(int $environmentId): array
    {
        $weekAgo = Carbon::now()->subDays(7);

        // Learner stats
        $totalLearners = EnvironmentUser::where('environment_id', $environmentId)->count();
        $newLearners = EnvironmentUser::where('environment_id', $environmentId)
            ->where('joined_at', '>=', $weekAgo)
            ->count();

        // Enrollment stats
        $newEnrollments = Enrollment::where('environment_id', $environmentId)
            ->where('enrolled_at', '>=', $weekAgo)
            ->count();

        // Completion stats
        $completions = Enrollment::where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $weekAgo)
            ->count();

        // Certificates
        $certificatesIssued = IssuedCertificate::where('environment_id', $environmentId)
            ->where('issued_date', '>=', $weekAgo)
            ->count();

        // Average feedback rating
        $avgRating = DB::table('feedback_answers')
            ->join('feedback_submissions', 'feedback_answers.feedback_submission_id', '=', 'feedback_submissions.id')
            ->join('feedback_contents', 'feedback_submissions.feedback_content_id', '=', 'feedback_contents.id')
            ->join('activities', 'feedback_contents.activity_id', '=', 'activities.id')
            ->join('blocks', 'activities.block_id', '=', 'blocks.id')
            ->join('templates', 'blocks.template_id', '=', 'templates.id')
            ->where('templates.environment_id', $environmentId)
            ->where('feedback_submissions.status', 'submitted')
            ->where('feedback_submissions.created_at', '>=', $weekAgo)
            ->whereNotNull('feedback_answers.answer_value')
            ->avg('feedback_answers.answer_value');

        // Top course by enrollments this week
        $topCourse = Enrollment::where('environment_id', $environmentId)
            ->where('enrolled_at', '>=', $weekAgo)
            ->select('course_id', DB::raw('COUNT(*) as enrollment_count'))
            ->groupBy('course_id')
            ->orderByDesc('enrollment_count')
            ->first();

        $topCourseData = null;
        if ($topCourse) {
            $course = \App\Models\Course::find($topCourse->course_id);
            if ($course) {
                $totalForCourse = Enrollment::where('course_id', $course->id)
                    ->where('environment_id', $environmentId)->count();
                $completedForCourse = Enrollment::where('course_id', $course->id)
                    ->where('environment_id', $environmentId)
                    ->where('status', 'completed')->count();

                $topCourseData = [
                    'title' => $course->title,
                    'enrollments' => $topCourse->enrollment_count,
                    'completion_rate' => $totalForCourse > 0
                        ? round(($completedForCourse / $totalForCourse) * 100)
                        : 0,
                ];
            }
        }

        // Upcoming events (next 7 days)
        $eventActivities = Activity::where('type', 'event')
            ->whereHas('block.template.courses', fn($q) => $q->where('environment_id', $environmentId))
            ->pluck('content_id');

        $upcomingEvents = EventContent::whereIn('id', $eventActivities)
            ->where('start_date', '>=', Carbon::now())
            ->where('start_date', '<=', Carbon::now()->addDays(7))
            ->orderBy('start_date')
            ->limit(3)
            ->get()
            ->map(fn($e) => [
                'title' => $e->title,
                'date' => $e->start_date->format('M d, g:i A'),
                'registrations' => \App\Models\EventRegistration::where('event_content_id', $e->id)->count(),
            ])->toArray();

        return [
            'total_learners' => $totalLearners,
            'new_learners' => $newLearners,
            'new_enrollments' => $newEnrollments,
            'completions' => $completions,
            'certificates_issued' => $certificatesIssued,
            'avg_rating' => round($avgRating ?? 0, 1),
            'top_course' => $topCourseData,
            'upcoming_events' => $upcomingEvents,
        ];
    }

    private function isInactive(array $stats): bool
    {
        return $stats['new_learners'] === 0
            && $stats['new_enrollments'] === 0
            && $stats['completions'] === 0
            && $stats['certificates_issued'] === 0;
    }
}
