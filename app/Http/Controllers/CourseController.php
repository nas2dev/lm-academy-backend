<?php

namespace App\Http\Controllers;

use Log;
use getID3;
use Validator;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MIN_PER_PAGE = 5;
    private const MAX_PER_PAGE = 100;

    public function getAllCourses(Request $request): mixed
    {
        try {
            $validator = Validator::make($request->all(), [
                "page" => "nullable|integer|min:1",
                "per_page" => "nullable|integer|min:" . self::MIN_PER_PAGE . "|max:" . self::MAX_PER_PAGE,
                "searchTerm" => "nullable|string|max:255"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $page = $request->input("page", 1);
            $perPage = $request->input("per_page", self::DEFAULT_PER_PAGE);
            $searchTerm = $request->input("searchTerm");

            $query = Course::with(['createdBy:id,first_name,last_name']);

            if (!empty($searchTerm)) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', '%' . $searchTerm . '%')
                        ->orWhereHas('createdBy', function ($cb) use ($searchTerm) {
                            $cb->where('first_name', 'like', '%' . $searchTerm . '%')
                                ->orWhere('last_name', 'like', '%' . $searchTerm . '%');
                        });
                });
            }

            $query->orderByDesc('created_at');

            $courses = $query->paginate($perPage, ['*'], 'page', $page);
            $courses->getCollection()->transform(function (Course $course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'duration' => (int) ceil($course->duration / 60),
                    'files' => $course->nr_of_files,
                    'first_name' => $course->createdBy->first_name,
                    'last_name' => $course->createdBy->last_name,
                    'created' => optional($course->created_at)->format('d.m.Y'),
                    'status' => ((int) $course->status) === 1 ? 'Active' : 'Inactive',
                ];
            });

            return response()->json([
                "success" => true,
                "message" => "Courses retrieved successfully",
                "courses" => $courses
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error getting all courses", [
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error getting all courses",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function deleteCourse(int $courseId): JsonResponse
    {
        try {
            $course = Course::find($courseId);

            if (!$course) {
                return response()->json([
                    "success" => false,
                    "message" => "Course not found.",
                ], 404);
            }

            DB::transaction(function () use ($course) {
                if ($course->intro_image_url && Storage::disk('public')->exists($course->intro_image_url)) {
                    Storage::disk('public')->delete($course->intro_image_url);
                }

                if ($course->intro_video_url && Storage::disk('public')->exists($course->intro_video_url)) {
                    Storage::disk('public')->delete($course->intro_video_url);
                }

                $course->delete();
            });

            return response()->json([
                "success" => true,
                "message" => "Course deleted successfully.",
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error deleting course", [
                "course_id" => $courseId,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to delete course. Please try again later.",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function changeCourseStatus(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                "course_id" => "required|integer|exists:courses,id",
                "status" => "required|integer|in:0,1"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $course = Course::with(['modules.sections.materials', 'createdBy:id,first_name,last_name'])
                ->find($request->input("course_id"));

            if (!$course) {
                return response()->json([
                    "success" => false,
                    "message" => "Course not found.",
                ], 404);
            }

            $requestedStatus = (int) $request->input("status");

            if ((int) $course->status === $requestedStatus) {
                return response()->json([
                    "success" => false,
                    "message" => "Course already has this status."
                ], 400);
            }

            if ($requestedStatus === 1) {
                $hasModule = $course->modules()->exists();
                $hasSections = $course->modules()->whereHas('sections')->exists();
                $hasMaterials = $course->modules()->whereHas("sections.materials")->exists();

                if (!$hasModule || !$hasSections || !$hasMaterials) {
                    return response()->json([
                        "success" => false,
                        "message" => "Cannot activate course until it has modules, sections and materials.",
                        "errors" => [
                            "modules" => $hasModule ? [] : ["At least one module is required."],
                            "sections" => $hasSections ? [] : ["At least one section is required."],
                            "materials" => $hasMaterials ? [] : ["At least one material is required."],
                        ]
                    ], 422);
                }
            }

            $course->status = $requestedStatus;
            $course->save();

            $course->refresh()->load(['modules', 'createdBy:id,first_name,last_name']);

            return response()->json(
                [
                    "success" => true,
                    "message" => "Course status updated successfully.",
                    'course' => [
                        'id' => $course->id,
                        'title' => $course->title,
                        'duration' => (int) ceil($course->duration / 60),
                        'files' => $course->nr_of_files,
                        'first_name' => $course->createdBy->first_name,
                        'last_name' => $course->createdBy->last_name,
                        'created' => optional($course->created_at)->format('d.m.Y'),
                        'status' => ((int) $course->status) === 1 ? 'Active' : 'Inactive',
                    ]
                ],
                200
            );
        } catch (\Exception $e) {
            \Log::error("Error changing course status", [
                "course_id" => $request->input("course_id"),
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to change course status. Please try again later.",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function createCourse(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                "title" => "required|string|max:255",
                "description" => "required|string",
                "thumbnail" => "nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120", // 5MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $thumbnailPath = null;

            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file("thumbnail")->store("course_thumbnails", "public");
            }

            $course = DB::transaction(function () use ($request, $user, $thumbnailPath) {
                return Course::create([
                    'title' => $request->input("title"),
                    "description" => $request->input("description"),
                    "intro_image_url" => $thumbnailPath,
                    "intro_video_url" => null,
                    "status" => 0,
                    "nr_of_files" => 0,
                    "duration" => 0,
                    "created_by" => $user->id,
                    "updated_by" => $user->id,
                ]);
            });

            $course->load("createdBy:id,first_name,last_name");

            return response()->json([
                "success" => true,
                "message" => "Course created successfully",
                "course" => [
                    "id" => $course->id,
                    "title" => $course->title,
                    "description" => $course->description,
                    "thumbnail" => $course->intro_image_url,
                    "duration" => (int) ceil($course->duration / 60),
                    "status" => $course->status ? 'Active' : 'Inactive',
                    "files" => $course->nr_of_files,
                    "created" => optional($course->created_at)->format('d.m.Y'),
                    'first_name' => $course->createdBy->first_name,
                    'last_name' => $course->createdBy->last_name,
                ]
            ], 201);
        } catch (\Exception $e) {

            if (!empty($thumbnailPath)) {
                Storage::disk("public")->delete($thumbnailPath);
            }

            \Log::error("Error creating course", [
                "user_id" => $request->user()->id,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to create course. Please try again later.",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function getCourseById(int $courseId): JsonResponse
    {
        try {
            $course = Course::with('createdBy:id,first_name,last_name')->find($courseId);

            if (!$course) {
                return response()->json([
                    "success" => false,
                    "message" => "Course not found.",
                ], 404);
            }

            return response()->json([
                "success" => true,
                "course" => [
                    "id" => $course->id,
                    "title" => $course->title,
                    "description" => $course->description,
                    "thumbnail" => $course->intro_image_url,
                    "duration" => (int) ceil($course->duration / 60),
                    "intro_video" => $course->intro_video_url,
                    "status" => $course->status ? 'Active' : 'Inactive',
                    "created" => optional($course->created_at)->format('d.m.Y'),
                    'first_name' => $course->createdBy->first_name,
                    'last_name' => $course->createdBy->last_name,

                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error getting course by id", [
                "course_id" => $courseId,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error getting course by id",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function updateCourse(Request $request, int $courseId): mixed
    {
        $thumbnailPath = null;
        try {
            $course = Course::with(['modules.sections.materials', 'createdBy:id,first_name,last_name'])->find($courseId);

            if (!$course) {
                return response()->json([
                    "success" => false,
                    "message" => "Course not found.",
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                "title" => 'required|string|max:255',
                "description" => 'required|string',
                "status" => "nullable|integer|in:0,1",
                "thumbnail" => "nullable|image|mimes:jpeg,jpg,png,gif,webp,svg|max:5120", // 5MB
                "remove_thumbnail" => "nullable|boolean",
                "remove_intro_video" => "nullable|boolean",
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $requestedStatus = $request->has("status") ? (int) $request->input("status") : (int) $course->status;

            if ($requestedStatus === 1) {
                $hasModules = $course->modules()->exists();
                $hasSections = $course->modules()->whereHas('sections')->exists();
                $hasMaterials = $course->modules()->whereHas("sections.materials")->exists();

                if (!$hasModules || !$hasSections || !$hasMaterials) {
                    return response()->json([
                        "success" => false,
                        "message" => "Cannot activate course until it has modules, sections and materials.",
                        "errors" => [
                            "modules" => $hasModules ? [] : ["At least one module is required."],
                            "sections" => $hasSections ? [] : ["At least one section is required."],
                            "materials" => $hasMaterials ? [] : ["At least one material is required."],
                        ]
                    ], 422);
                }
            }

            $removeThumbnail = $request->boolean('remove_thumbnail');
            $removeIntroVideo = $request->boolean('remove_intro_video');

            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('course_thumbnails', 'public');
            }

            $user = $request->user();

            DB::transaction(function () use ($course, $request, $user, $thumbnailPath, $removeThumbnail, $removeIntroVideo, $requestedStatus) {
                if ($thumbnailPath) {
                    if ($course->intro_image_url) {
                        Storage::disk('public')->delete($course->intro_image_url);
                    }
                    $course->intro_image_url = $thumbnailPath;
                } else if ($removeThumbnail && $course->intro_image_url) {
                    Storage::disk('public')->delete($course->intro_image_url);
                    $course->intro_image_url = null;
                }

                // TODO: Analyze again after chunk video upload is implemented
                if ($removeIntroVideo && $course->intro_video_url) {
                    Storage::disk('public')->delete($course->intro_video_url);
                    $course->intro_video_url = null;
                }

                $course->title = $request->input('title');
                $course->description = $request->input('description');
                $course->status = $requestedStatus;
                $course->updated_by = $user->id;
                $course->save();
            });

            $course->refresh()->load('createdBy:id,first_name,last_name');

            return response()->json([
                "success" => true,
                "message" => "Course updated successfully",
                "course" => [
                    "id" => $course->id,
                    "title" => $course->title,
                    "description" => $course->description,
                    "thumbnail" => $course->intro_image_url,
                    "intro_video" => $course->intro_video_url,
                    "duration" => (int) ceil($course->duration / 60),
                    "files" => $course->nr_of_files,
                    "status" => $course->status ? 'Active' : 'Inactive',
                    "created" => optional($course->created_at)->format('d.m.Y'),
                    "created_by" => $course->createdBy ? trim(($course->createdBy->first_name . ' ' . $course->createdBy->last_name)) : null,
                ]
            ], 200);
        } catch (\Exception $e) {
            if ($thumbnailPath) {
                Storage::disk('public')->delete($thumbnailPath);
            }

            \Log::error("Error updating course", [
                "course_id" => $courseId,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error updating course",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteCourseVideo(Request $request, int $courseId): mixed
    {
        try {
            $course = Course::find($courseId);

            if (!$course) {
                return response()->json([
                    "success" => false,
                    "message" => "Course not found.",
                ], 404);
            }

            if (!$course->intro_video_url) {
                return response()->json([
                    "success" => false,
                    "message" => "Course does not have an intro video.",
                ], 404);
            }

            DB::transaction(function () use ($course) {
                // Get video duration before deleting
                $oldDuration = 0;
                if (Storage::disk('public')->exists($course->intro_video_url)) {
                    $getID3 = new getID3();
                    $videoPath = Storage::disk('public')->path($course->intro_video_url);
                    $fileInfo = $getID3->analyze($videoPath);
                    // dd($fileInfo);
                    if (isset($fileInfo['playtime_seconds'])) {
                        $oldDuration = floor($fileInfo['playtime_seconds']);
                    }
                    Storage::disk('public')->delete($course->intro_video_url);
                }

                // Update course
                $course->intro_video_url = null;
                $course->nr_of_files = max(0, $course->nr_of_files - 1);
                $course->duration = max(0, $course->duration - $oldDuration);
                $course->save();
            });

            $course->refresh()->load('createdBy:id,first_name,last_name');

            return response()->json([
                "success" => true,
                "message" => "Course video deleted successfully.",
                "course" => [
                    "id" => $course->id,
                    "title" => $course->title,
                    'description' => $course->description,
                    'thumbnail' => $course->intro_image_url,
                    'intro_video' => null,
                    'duration' => (int) ceil($course->duration / 60),
                    'files' => $course->nr_of_files,
                    'status' => $course->status ? 'Active' : 'Inactive',
                    'created' => optional($course->created_at)->format('d.m.Y'),
                    'created_by' => $course->createdBy ? trim(($course->createdBy->first_name . ' ' . $course->createdBy->last_name)) : null,
                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error deleting course video", [
                "course_id" => $courseId,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error deleting course video",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllActiveCourses(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                "page" => "nullable|integer|min:1",
                "per_page" => "nullable|integer|min:" . self::MIN_PER_PAGE . "|max:" . self::MAX_PER_PAGE,
                "searchTerm" => "nullable|string|max:255"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $page = $request->input("page", 1);
            $perPage = $request->input("per_page", self::DEFAULT_PER_PAGE);
            $searchTerm = $request->input("searchTerm");

            // Build the query for active courses only
            $query = Course::with(['createdBy:id,first_name,last_name'])
                ->where('status', 1);

            // Apply search filter if search term is provided
            if (!empty($searchTerm)) {
                $searchTermLower = strtolower($searchTerm);
                $query->whereRaw('LOWER(title) LIKE ?', ["%{$searchTermLower}%"]);
            }

            $query->orderByDesc('created_at');

            $courses = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform courses and add user course status
            $courses->getCollection()->transform(function (Course $course) use ($user) {
                $courseData = [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => $course->description,
                    'thumbnail' => $course->intro_image_url,
                    'duration' => (int) ceil($course->duration / 60),  // Convert to minutes
                    'files' => $course->nr_of_files,
                    'created_at' => optional($course->created_at)->format('d.m.Y'),
                ];

                // Add user course status
                $courseProgress = $course->progress->first();

                if (!$courseProgress) {
                    $courseData['user_progress'] = 'new';
                    $courseData['status_label'] = 'New!';
                } else if ($courseProgress->awarded) {
                    $courseData['user_progress'] = 'completed';
                    $courseData['status_label'] = 'Completed';
                } else {
                    $courseData['user_progress'] = 'progressing';
                    $courseData['status_label'] = 'Progressing';
                }

                return $courseData;
            });

            return response()->json([
                "success" => true,
                "message" => "Courses retrieved successfully",
                "courses" => $courses
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error getting all active courses", [
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error getting all active courses",
                "error" => $e->getMessage(),
            ], 500);
        }
    }
}
