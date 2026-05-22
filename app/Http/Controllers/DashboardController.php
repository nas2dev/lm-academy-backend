<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Scoreboard;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\UserCourseProgress;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard
     *
     * Returns aggregated dashboard data based on the authenticated user's role.
     * - Students (role: User): enrollment stats, score, rank, and active courses with progress.
     * - Admins (role: Admin): platform-wide stats and course overview.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->hasRole("Admin")) {
                return $this->adminDashboard();
            }

            return $this->studentDashboard($user);
        } catch (\Exception $e) {
            Log::error("Error fetching dashboard data", [
                "user_id" => $request->user()->id ?? null,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to fetch dashboard data",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build dashboard response for a student (User role).
     */
    private function studentDashboard(User $user): JsonResponse
    {
        // Get all course progress records for this user (to calculate statistics)
        $allProgress = UserCourseProgress::where('user_id', $user->id)->get();

        $enrolledCount = $allProgress->count();
        $completedCount = $allProgress->where('awarded', true)->count();
        $inProgressCount = $enrolledCount - $completedCount;

        // Score & rank from scoreboard
        $scoreboard = Scoreboard::where('user_id', $user->id)->first();
        $myScore = $scoreboard ? (int) $scoreboard->score : 0;

        // Rank: count users with a higher score + 1 (ties share the same rank)
        $myRank = $scoreboard
            ? Scoreboard::where('score', '>', $scoreboard->score)->count() + 1
            : null;

        // Active courses: limit to latest 10 updated progress records
        $activeProgress = UserCourseProgress::with('course')
            ->where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->take(10)
            ->get();

        // Active courses with progress percentage
        $activeCourses = $activeProgress->map(function ($progress) {
            $totalSections = $progress->completed_sections + ($progress->pending_sections ?? 0);
            $totalModules = $progress->completed_modules + ($progress->pending_modules ?? 0);
            $totalItems = $totalSections + $totalModules;
            $completedItems = $progress->completed_sections + $progress->completed_modules;

            $percentage = $totalItems > 0
                ? round(($completedItems / $totalItems) * 100)
                : 0;

            return [
                'id' => $progress->course->id,
                'title' => $progress->course->title,
                'progress' => (int) $percentage,
                'thumbnail' => $progress->course->intro_image_url,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'enrolled_courses' => $enrolledCount,
                'completed_courses' => $completedCount,
                'in_progress_courses' => $inProgressCount,
                'my_score' => $myScore,
                'my_rank' => $myRank,
                'active_courses' => $activeCourses,
            ]
        ], 200);
    }

    /**
     * Build dashboard response for an admin.
     */
    private function adminDashboard(): JsonResponse
    {
        // Total active students (users with 'User' role and active account)
        $totalStudents = User::whereHas('roles', function ($query) {
            $query->where('name', 'User');
        })
            ->where('acc_status', 1)
            ->count();

        $totalCourses = Course::count();
        $activeEnrollments = UserCourseProgress::count();

        // Average completion percentage across all enrollments
        $allProgress = UserCourseProgress::all();
        $avgCompletion = 0;

        if ($allProgress->isNotEmpty()) {
            $totalPercentage = $allProgress->sum(function ($progress) {
                $totalSections = $progress->completed_sections + ($progress->pending_sections ?? 0);
                $totalModules = $progress->completed_modules + ($progress->pending_modules ?? 0);
                $totalItems = $totalSections + $totalModules;
                $completedItems = $progress->completed_sections + $progress->completed_modules;

                return $totalItems > 0 ? ($completedItems / $totalItems) * 100 : 0;
            });

            $avgCompletion = (int) round($totalPercentage / $allProgress->count());
        }

        // Course overview with enrollment counts (latest 10 courses)
        $courses = Course::withCount(['progress as enrolled_count'])
            ->latest()
            ->take(10)
            ->get();
        $courseOverview = $courses->map(function ($course) {
            return [
                'id' => $course->id,
                'title' => $course->title,
                'enrolled_count' => $course->enrolled_count,
                'thumbnail' => $course->intro_image_url,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total_students' => $totalStudents,
                'total_courses' => $totalCourses,
                'active_enrollments' => $activeEnrollments,
                'avg_completion' => $avgCompletion,
                'course_overview' => $courseOverview,
            ]
        ], 200);
    }
}
