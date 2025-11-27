<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityCompletion;
use App\Models\Enrollment;
use App\Models\FeedbackSubmission;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LearnerController extends Controller
{
    /**
     * Get detailed information about a learner including their enrollments and submissions
     *
     * @param  int  $userId
     * @return \Illuminate\Http\Response
     */
    public function show($userId)
    {
        $environmentId = session("current_environment_id");

        // Get the user (profile_photo_path is the actual column, profile_photo_url is an accessor)
        $user = User::select('id', 'name', 'email', 'profile_photo_path', 'created_at')
            ->findOrFail($userId);

        // Get all enrollments for this user in the current environment
        $enrollments = Enrollment::where('user_id', $userId)
            ->where('environment_id', $environmentId)
            ->with(['course' => function ($query) {
                $query->with('template:id,title');
            }, 'activityCompletions'])
            ->get();

        // Calculate enrollment statistics
        $totalEnrollments = $enrollments->count();
        $completedEnrollments = $enrollments->where('status', 'completed')->count();
        $inProgressEnrollments = $enrollments->where('status', 'in-progress')->count();
        $averageProgress = $enrollments->avg('progress_percentage') ?? 0;

        // Get feedback submissions for this user
        $feedbackSubmissions = FeedbackSubmission::where('user_id', $userId)
            ->with(['feedbackContent.activity.block.template', 'answers.question'])
            ->whereHas('feedbackContent.activity.block.template', function ($query) use ($environmentId) {
                $query->where('environment_id', $environmentId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Get assignment submissions for this user
        $assignmentSubmissions = AssignmentSubmission::where('user_id', $userId)
            ->with(['assignmentContent.activity.block.template'])
            ->whereHas('assignmentContent.activity.block.template', function ($query) use ($environmentId) {
                $query->where('environment_id', $environmentId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Get quiz completions from activity completions (quiz scores are stored in ActivityCompletion)
        $quizCompletions = ActivityCompletion::whereHas('enrollment', function ($query) use ($userId, $environmentId) {
            $query->where('user_id', $userId)
                ->where('environment_id', $environmentId);
        })
            ->whereHas('activity', function ($query) {
                $query->where('type', 'quiz');
            })
            ->with(['activity.block.template:id,title', 'enrollment.course:id,title'])
            ->whereNotNull('score')
            ->orderBy('completed_at', 'desc')
            ->get();

        // Calculate performance metrics
        $totalFeedbackSubmissions = $feedbackSubmissions->count();
        $totalAssignmentSubmissions = $assignmentSubmissions->count();

        // Calculate average rating from feedback (if applicable)
        $averageRating = null;
        $ratingCount = 0;
        foreach ($feedbackSubmissions as $submission) {
            foreach ($submission->answers as $answer) {
                if ($answer->answer_value !== null) {
                    $averageRating = ($averageRating ?? 0) + $answer->answer_value;
                    $ratingCount++;
                }
            }
        }
        if ($ratingCount > 0) {
            $averageRating = round($averageRating / $ratingCount, 2);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $user,
                'statistics' => [
                    'total_enrollments' => $totalEnrollments,
                    'completed_enrollments' => $completedEnrollments,
                    'in_progress_enrollments' => $inProgressEnrollments,
                    'average_progress' => round($averageProgress, 1),
                    'total_feedback_submissions' => $totalFeedbackSubmissions,
                    'total_assignment_submissions' => $totalAssignmentSubmissions,
                    'average_rating' => $averageRating,
                ],
                'enrollments' => $enrollments,
                'feedback_submissions' => $feedbackSubmissions,
                'assignment_submissions' => $assignmentSubmissions,
                'quiz_completions' => $quizCompletions,
            ],
        ]);
    }

    /**
     * Get submissions for a specific enrollment
     *
     * @param  int  $userId
     * @param  int  $enrollmentId
     * @return \Illuminate\Http\Response
     */
    public function getEnrollmentSubmissions($userId, $enrollmentId)
    {
        $environmentId = session("current_environment_id");

        // Get the enrollment
        $enrollment = Enrollment::where('id', $enrollmentId)
            ->where('user_id', $userId)
            ->where('environment_id', $environmentId)
            ->with(['course.template'])
            ->firstOrFail();

        $templateId = $enrollment->course->template_id;

        // Get feedback submissions for this template
        $feedbackSubmissions = FeedbackSubmission::where('user_id', $userId)
            ->with(['feedbackContent.activity', 'answers.question'])
            ->whereHas('feedbackContent.activity.block', function ($query) use ($templateId) {
                $query->where('template_id', $templateId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Get assignment submissions for this template
        $assignmentSubmissions = AssignmentSubmission::where('user_id', $userId)
            ->with(['assignmentContent.activity'])
            ->whereHas('assignmentContent.activity.block', function ($query) use ($templateId) {
                $query->where('template_id', $templateId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Get activity completions for this enrollment
        $activityCompletions = $enrollment->activityCompletions()
            ->with('activity')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'enrollment' => $enrollment,
                'feedback_submissions' => $feedbackSubmissions,
                'assignment_submissions' => $assignmentSubmissions,
                'activity_completions' => $activityCompletions,
            ],
        ]);
    }
}
