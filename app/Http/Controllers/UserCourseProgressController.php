<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\UserCourseProgress;
use Illuminate\Support\Facades\Log;

class UserCourseProgressController extends Controller
{
    public function getUserCourseProgress(Request $request): JsonResponse
    {
        try {
            // Get filter parameters
            $courseId = $request->input("course_id", 'all');
            $userId = $request->input("user_id", 'all');

            // Filter courses
            if ($courseId !== 'all') {
                $course = Course::where('status', 1)->find($courseId);
                if (!$course) {
                    return response()->json([
                        "success" => false,
                        "message" => "Course not found or not active"
                    ], 404);
                }

                $courses = [$course->id];
            } else {
                // Get all active courses
                $courses = Course::where('status', 1)
                    ->orderBy('title', 'asc')
                    ->pluck('id')
                    ->toArray();
            }

            // Filter users (only users with 'User' role)
            if ($userId !== 'all') {
                $user = User::whereHas("roles", function ($query) {
                    $query->where("name", "User");
                })
                    ->where("acc_status", 1) // Only active users
                    ->find($userId);

                if (!$user) {
                    return response()->json([
                        "success" => false,
                        "message" => "User not found or not active"
                    ], 404);
                }

                $users = [$user->id];
            } else {
                // Get all users with "User" role (exclude admins)
                $users = User::whereHas("roles", function ($query) {
                    $query->where("name", "User");
                })
                    ->where("acc_status", 1) // Only active users
                    ->pluck('id')
                    ->toArray();
            }

            // Get user course progress with relationships
            $userCourseProgress = UserCourseProgress::with([
                "user:id,first_name,last_name,email",
                'course:id,title'
            ])
                ->whereIn('course_id', $courses)
                ->whereIn('user_id', $users)
                ->get();

            // Calculate progress for each record
            foreach ($userCourseProgress as $progress) {
                // Total sections and modules
                $totalSections = $progress->completed_sections + ($progress->pending_sections ?? 0);
                $totalModules = $progress->completed_modules + ($progress->pending_modules ?? 0);

                // Completed sections and modules
                $completedSections = $progress->completed_sections;
                $completedModules = $progress->completed_modules;

                // Total and completed progress items
                $totalItems = $totalSections + $totalModules;
                $completedItems = $completedSections + $completedModules;

                // Calculate unified progress percentage
                $overallCompletion = ($totalItems > 0) ? ($completedItems / $totalItems) * 100 : 0;

                // Determine status based on percentage
                if ($overallCompletion == 100) {
                    $progress->completion_status = 'Completed';
                } else if ($overallCompletion >= 60) {
                    $progress->completion_status = 'Close';
                } else if ($overallCompletion >= 40) {
                    $progress->completion_status = 'Progressing';
                } else {
                    $progress->completion_status = 'Started';
                }

                // Add the percentage to the response object
                $progress->completion_percentage = round($overallCompletion, 2);

                $progress->started_date = $progress->created_at ? $progress->created_at->format('d.m.Y') : null;
            }

            // Determine progress message if no results
            $progressMessage = '';
            if ($userCourseProgress->isEmpty()) {
                $progressMessage = [
                    'all_all' => 'No users enrolled in any courses.',
                    'all_specific' => 'No users enrolled in this course.',
                    'specific_all' => 'This user is not enrolled in any courses.',
                    'specific_specific' => 'This user is not enrolled in this course.'
                ];

                $progressKey = ($userId == 'all' ? 'all' : 'specific') . '_' . ($courseId == 'all' ? 'all' : 'specific');
                $progressMessage = $progressMessage[$progressKey] ?? 'No progress data found.';
            }

            return response()->json([
                "success" => true,
                "message" => "User course progress retrieved successfully",
                'progressMessage' => $progressMessage,
                "data" => $userCourseProgress
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error getting user course progress", [
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
            ]);
        }
    }
}
