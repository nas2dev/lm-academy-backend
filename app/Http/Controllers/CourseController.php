<?php

namespace App\Http\Controllers;

use Log;
use getID3;
use Validator;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\UserCourseProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\UserCourseSectionProgress;

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

    public function getCourseDetailsForUser(Request $request, int $courseId): JsonResponse
    {
        try {
            $user = $request->user();

            // Fetch course with modules and sections
            $course = Course::with([
                'modules.sections',
                'createdBy:id,first_name,last_name',
                'updatedBy:id,first_name,last_name'
            ])->where('id', $courseId)
                ->first();

            if (!$course) {
                return response()->json([
                    "success" => false,
                    "message" => "Course not found or not available.",
                ], 404);
            }

            // Calculate totals
            $totalModules = $course->modules->count();
            $totalSections = $course->modules->sum(function ($module) {
                return $module->sections->count();
            });

            // Add total_sections to each module
            $course->modules = $course->modules->map(function ($module) {
                $module->total_sections = $module->sections->count();
                return $module;
            });

            // Get user course progress
            $userProgress = UserCourseProgress::where('course_id', $course->id)
                ->where('user_id', $user->id)
                ->first();

            // Determine course status
            $courseStatus = 'new';
            if ($userProgress) {
                if ($userProgress->pending_sections == 0 && $userProgress->pending_modules == 0) {
                    $courseStatus = 'completed';
                } else {
                    $courseStatus = 'progressing';
                }
            }

            // Get total users enrolled in the course
            $totalUsersEnrolled = UserCourseProgress::where('course_id', $course->id)->count();

            // Format response
            return response()->json([
                "success" => true,
                "message" => "Course details retrieved successfully",
                "course" => [
                    "id" => $course->id,
                    "title" => $course->title,
                    "description" => $course->description,
                    "thumbnail" => $course->intro_image_url,
                    'intro_video' => $course->intro_video_url,
                    'duration' => (int) ceil($course->duration / 60), // convert to minutes
                    'files' => $course->nr_of_files,
                    'created_at' => optional($course->created_at)->format('Y-m-d H:i:s'),
                    'updated_at' => optional($course->updated_at)->format('Y-m-d H:i:s'),
                    'created_by' => $course->createdBy ? trim(($course->createdBy->first_name . ' ' . $course->createdBy->last_name)) : null,
                    'updated_by' => $course->updatedBy ? trim(($course->updatedBy->first_name . ' ' . $course->updatedBy->last_name)) : null,
                    'modules' => $course->modules->map(function ($module) {
                        return [
                            'id' => $module->id,
                            'title' => $module->title,
                            'description' => $module->description,
                            'duration' => (int) ceil($module->duration / 60), // convert to minutes
                            'files' => $module->nr_of_files,
                            'total_sections' => $module->total_sections,
                            'sections' => $module->sections->map(function ($section) {
                                return [
                                    'id' => $section->id,
                                    'title' => $section->title,
                                    'description' => $section->description,
                                    'duration' => (int) ceil($section->duration / 60), // convert to minutes
                                    'files' => $section->nr_of_files,
                                ];
                            }),
                        ];
                    }),
                ],
                'total_modules' => $totalModules,
                'total_sections' => $totalSections,
                'totalUsersEnrolled' => $totalUsersEnrolled,
                'courseStatus' => $courseStatus,
            ]);
        } catch (\Exception $e) {
            \Log::error("Error getting course details for user", [
                "course_id" => $courseId,
                "user_id" => $user->id,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error getting course details for user",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function enrollUser(Request $request, int $courseId): JsonResponse
    {
        try {
            $user = $request->user();

            // Fetch course with modules and sections
            $course = Course::with('modules.sections')
                ->where('id', $courseId)
                ->where('status', 1)
                ->first();

            if (!$course) {
                return response()->json([
                    "success" => false,
                    "message" => "Course not found or not available.",
                ], 404);
            }

            // Check if the user is already enrolled in the course
            $existingEnrollment = UserCourseProgress::where('course_id', $course->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingEnrollment) {
                return response()->json([
                    "success" => false,
                    "message" => "You are already enrolled in this course.",
                ], 409);
            }

            // Calculate total modules and sections
            $totalModules = $course->modules->count();
            $totalSections = $course->modules->sum(function ($module) {
                return $module->sections->count();
            });

            // Create new user enrollment record
            $userCourseProgress = new UserCourseProgress();
            $userCourseProgress->user_id = $user->id;
            $userCourseProgress->course_id = $course->id;
            $userCourseProgress->pending_modules = $totalModules;
            $userCourseProgress->pending_sections = $totalSections;
            $userCourseProgress->completed_modules = 0;
            $userCourseProgress->completed_sections = 0;
            $userCourseProgress->completed_module_ids = [];
            $userCourseProgress->completed_section_ids = [];
            $userCourseProgress->awarded = false;
            $userCourseProgress->save();

            return response()->json([
                "success" => true,
                "message" => "You have been enrolled in the course successfully.",
                "course" => [
                    "id" => $course->id,
                    "title" => $course->title,
                ]
            ], 201);
        } catch (\Exception $e) {
            \Log::error("Error enrolling user in course", [
                "course_id" => $courseId,
                "user_id" => $user->id,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);
        }

        return response()->json([
            "success" => false,
            "message" => "Error enrolling user in course",
            "error" => $e->getMessage(),
        ], 500);
    }

    public function showCourseModules(Request $request, int $courseId): JsonResponse
    {
        try {
            $user = $request->user();

            // Fetch course with modules and sections
            $course = Course::with(['modules.sections', 'createdBy:id,first_name,last_name', 'updatedBy:id,first_name,last_name'])
                ->where('id', $courseId)
                ->where('status', 1)
                ->first();

            if (!$course) {
                return response()->json([
                    "success" => false,
                    "message" => "Course not found or not available.",
                ], 404);
            }

            // Check if user is enrolled in the course
            $userProgress = UserCourseProgress::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            if (!$userProgress) {
                return response()->json([
                    "success" => false,
                    "message" => "You are not enrolled in this course.",
                ], 403);
            }

            // Fetch all completed section IDs for this user and course
            $allSectionIds = $course->modules->flatMap(function ($module) {
                return $module->sections->pluck('id');
            })->toArray();

            $completedSectionIds = UserCourseSectionProgress::where('user_id', $user->id)
                ->whereIn('course_section_id', $allSectionIds)
                ->pluck('course_section_id')
                ->toArray();

            // Map modules with progress
            $moduleData = $course->modules->map(function ($module) use ($completedSectionIds) {
                // Calculate user progress for this module
                $moduleSectionIds = $module->sections->pluck('id')->toArray();
                $completedModuleSections = array_intersect($completedSectionIds, $moduleSectionIds);
                $totalSections = count($moduleSectionIds);
                $moduleProgress = $totalSections > 0 ? (count($completedModuleSections) / $totalSections) * 100 : 0;

                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'progress' => round($moduleProgress),
                    'total_sections' => $totalSections,
                    'formatted_duration' => $this->formatDuration($module->duration),
                    'sections' => $module->sections->map(function ($section) use ($completedSectionIds) {
                        return [
                            'id' => $section->id,
                            'title' => $section->title,
                            'nr_of_files' => $section->nr_of_files,
                            'duration' => $this->formatDuration($section->duration),
                            'completed' => in_array($section->id, $completedSectionIds)
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Course modules retrieved successfully',
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => $course->description,
                    'created_by' => $course->createdBy ? trim(($course->createdBy->first_name . ' ' . $course->createdBy->last_name)) : null,
                    'updated_by' => $course->updatedBy ? trim(($course->updatedBy->first_name . ' ' . $course->updatedBy->last_name)) : null,
                    'modules' => $moduleData
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error("Error showing course modules", [
                "course_id" => $courseId,
                "user_id" => $user->id,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);
        }

        return response()->json([
            "success" => false,
            "message" => "Error showing course modules",
            "error" => $e->getMessage(),
        ], 500);
    }


    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }
}
