<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\CourseModule;
use Illuminate\Support\Facades\DB;

class CourseModuleController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MIN_PER_PAGE = 5;
    private const MAX_PER_PAGE = 100;

    public function getAllModules(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                "page" => "nullable|integer|min:1",
                "per_page" => "nullable|integer|min:" . self::MIN_PER_PAGE . "|max:" . self::MAX_PER_PAGE,
                "searchTerm" => "nullable|string|max:255",
                "course_id" => "nullable|integer|exists:courses,id"
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
            $courseId = $request->input("course_id");

            $query = CourseModule::withCount('sections');

            if (!empty($courseId)) {
                $query->where('course_id', $courseId);
            }

            if (!empty($searchTerm)) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', '%' . $searchTerm . '%')
                        ->orWhere('description', 'like', '%' . $searchTerm . '%');
                });
            }

            $query->orderByDesc("created_at");

            $modules = $query->paginate($perPage, ['*'], 'page', $page);

            $modules->getCollection()->transform(function (CourseModule $module) {
                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'section_nr' => (int) $module->sections_count,
                ];
            });
            return response()->json([
                "success" => true,
                "message" => "Modules retrieved successfully",
                "modules" => $modules
            ]);
        } catch (\Exception $e) {
            \Log::error("Error retrieving modules", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to retrieve modules. Please try again later.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function deleteModule(int $moduleId): JsonResponse
    {
        try {
            $module = CourseModule::find($moduleId);

            if (!$module) {
                return response()->json([
                    "success" => false,
                    "message" => "Module not found.",
                ], 404);
            }

            $course = $module->course;

            DB::transaction(function () use ($module, $course) {
                $moduleDuration = $module->duration ?? 0;
                $moduleFiles = $module->nr_of_files ?? 0;

                $module->delete();

                if ($course) {
                    $course->duration = max(0, ($course->duration ?? 0) - $moduleDuration);
                    $course->nr_of_files = max(0, ($course->nr_of_files ?? 0) - $moduleFiles);
                    $course->save();
                }
            });

            return response()->json([
                "success" => true,
                "message" => "Module deleted successfully.",
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error deleting module", [
                'module_id' => $moduleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to delete module. Please try again later.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function createModule(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                "description" => "required|string",
                "course_id" => "required|integer|exists:courses,id"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $module = DB::transaction(function () use ($request) {
                return CourseModule::create([
                    'course_id' => $request->input("course_id"),
                    'title' => $request->input("title"),
                    'description' => $request->input("description"),
                    'nr_of_files' => 0,
                    'duration' => 0,
                ]);
            });

            return response()->json([
                "success" => true,
                "message" => "Module created successfully.",
                "module" => [
                    "id" => $module->id,
                    "title" => $module->title,
                    "description" => $module->description,
                    "section_nr" => 0
                ]
            ], 201);
        } catch (\Exception $e) {
            \Log::error("Error creating module", [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                "course_id" => $request->input("course_id"),
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to create module. Please try again later.",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function getModuleById(int $moduleId): JsonResponse
    {
        try {
            $module = CourseModule::find($moduleId);

            if (!$module) {
                return response()->json([
                    "success" => false,
                    "message" => "Module not found.",
                ], 404);
            }

            return response()->json([
                "success" => true,
                "message" => "Module retrieved successfully.",
                "module" => [
                    "id" => $module->id,
                    "title" => $module->title,
                    "description" => $module->description,
                    "course_id" => $module->course_id,
                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error creating module", [
                'module_id' => $moduleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),

            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to retrieve module. Please try again later.",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function updateModule(Request $request, int $moduleId): JsonResponse
    {
        try {
            $module = CourseModule::find($moduleId);

            if (!$module) {
                return response()->json([
                    "success" => false,
                    "message" => "Module not found.",
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                "description" => "required|string",
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            DB::transaction(function () use ($module, $request) {
                $module->title = $request->input("title");
                $module->description = $request->input("description");
                $module->save();
            });

            $module->refresh();
            $module->load('sections');

            return response()->json([
                "success" => true,
                "message" => "Module updated successfully.",
                "module" => [
                    "id" => $module->id,
                    "title" => $module->title,
                    "description" => $module->description,
                    "section_nr" => (int) $module->sections_count,
                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error updating module", [
                'module_id' => $moduleId,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),

            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to update module. Please try again later.",
                "error" => $e->getMessage(),
            ], 500);
        }
    }
}
