<?php

namespace App\Console\Commands;

use App\Mail\LearnerWeeklyDigest;
use App\Models\ActivityCompletion;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\IssuedCertificate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendLearnerWeeklyDigest extends Command
{
    protected $signature = 'engagement:learner-digest {--dry-run : Preview stats without sending emails}';
    protected $description = 'Send weekly progress digest emails to learners across all active environments';

    public function handle(): int
    {
        $this->info('Starting learner weekly digest...');

        $environments = Environment::where('is_active', true)->get();

        $totalSent = 0;
        $totalSkipped = 0;

        foreach ($environments as $environment) {
            $this->line("Processing: {$environment->name}...");

            // Get learner user IDs for this environment
            $learnerUserIds = EnvironmentUser::where('environment_id', $environment->id)
                ->pluck('user_id');

            if ($learnerUserIds->isEmpty()) {
                $this->line("  No learners, skipping.");
                continue;
            }

            // Process in chunks to avoid memory issues
            User::whereIn('id', $learnerUserIds)
                ->whereNotNull('email')
                ->chunk(50, function ($users) use ($environment, &$totalSent, &$totalSkipped) {
                    foreach ($users as $user) {
                        try {
                            $stats = $this->gatherLearnerStats($user->id, $environment->id);

                            // Skip if zero activity
                            if ($this->isInactive($stats)) {
                                $totalSkipped++;
                                continue;
                            }

                            if ($this->option('dry-run')) {
                                $this->info("  DRY-RUN: {$user->email} — {$stats['activities_completed']} activities, {$stats['active_enrollments']} courses");
                            } else {
                                Mail::to($user->email)
                                    ->send(new LearnerWeeklyDigest($user, $environment, $stats));
                            }

                            $totalSent++;
                        } catch (\Exception $e) {
                            Log::error("Learner digest failed for user #{$user->id} in env #{$environment->id}: {$e->getMessage()}");
                            $this->error("  Failed for {$user->email}: {$e->getMessage()}");
                        }
                    }
                });
        }

        $this->info("Done. Sent: {$totalSent}, Skipped: {$totalSkipped}");
        return self::SUCCESS;
    }

    private function gatherLearnerStats(int $userId, int $environmentId): array
    {
        $weekAgo = Carbon::now()->subDays(7);

        // Active enrollments with progress
        $enrollments = Enrollment::where('user_id', $userId)
            ->where('environment_id', $environmentId)
            ->whereIn('status', ['enrolled', 'in-progress', 'completed'])
            ->with('course:id,title')
            ->get();

        $courses = $enrollments->map(fn($e) => [
            'title' => $e->course?->title ?? 'Unknown Course',
            'progress' => round($e->progress_percentage ?? 0),
            'status' => $e->status,
        ])->toArray();

        $activeEnrollments = $enrollments->whereIn('status', ['enrolled', 'in-progress'])->count();

        // Activities completed this week
        $activitiesCompleted = ActivityCompletion::whereHas('enrollment', fn($q) =>
            $q->where('user_id', $userId)->where('environment_id', $environmentId)
        )->where('completed_at', '>=', $weekAgo)->count();

        // Certificates earned this week
        $certificatesEarned = IssuedCertificate::where('environment_id', $environmentId)
            ->where('user_id', $userId)
            ->where('issued_date', '>=', $weekAgo)
            ->count();

        // Upcoming assignment deadlines (next 7 days)
        $enrollmentIds = $enrollments->pluck('id');
        $upcomingDeadlines = [];

        if ($enrollmentIds->isNotEmpty()) {
            $deadlines = DB::table('assignment_contents')
                ->join('activities', function ($join) {
                    $join->on('activities.content_id', '=', 'assignment_contents.id')
                        ->where('activities.content_type', '=', 'App\\Models\\AssignmentContent');
                })
                ->join('course_section_items', 'course_section_items.activity_id', '=', 'activities.id')
                ->join('course_sections', 'course_sections.id', '=', 'course_section_items.course_section_id')
                ->join('courses', 'courses.id', '=', 'course_sections.course_id')
                ->join('enrollments', 'enrollments.course_id', '=', 'courses.id')
                ->where('enrollments.user_id', $userId)
                ->where('enrollments.environment_id', $environmentId)
                ->where('assignment_contents.due_date', '>=', Carbon::now())
                ->where('assignment_contents.due_date', '<=', Carbon::now()->addDays(7))
                ->select(
                    'activities.title',
                    'assignment_contents.due_date',
                    'courses.title as course_title'
                )
                ->orderBy('assignment_contents.due_date')
                ->limit(3)
                ->get();

            $upcomingDeadlines = $deadlines->map(fn($d) => [
                'title' => $d->title,
                'due_date' => Carbon::parse($d->due_date)->format('M d, g:i A'),
                'course' => $d->course_title,
            ])->toArray();
        }

        return [
            'active_enrollments' => $activeEnrollments,
            'activities_completed' => $activitiesCompleted,
            'certificates_earned' => $certificatesEarned,
            'courses' => $courses,
            'upcoming_deadlines' => $upcomingDeadlines,
        ];
    }

    private function isInactive(array $stats): bool
    {
        return $stats['activities_completed'] === 0
            && $stats['certificates_earned'] === 0
            && empty($stats['upcoming_deadlines'])
            && empty($stats['courses']);
    }
}
