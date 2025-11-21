<?php

namespace App\Http\Controllers;

use App\Models\CourseSection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CourseSectionController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MIN_PER_PAGE = 5;
    private const MAX_PER_PAGE = 100;

    public function getAllSections(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                "page" => "nullable|integer|min:1",
                "per_page" => "nullable|integer|min:" . self::MIN_PER_PAGE . "|max:" . self::MAX_PER_PAGE,
                "searchTerm" => "nullable|string|max:255",
                "module_id" => "nullable|integer|exists:course_modules,id"
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
            $moduleId = $request->input("module_id");

            $query = CourseSection::withCount('materials');

            if (!empty($moduleId)) {
                $query->where('module_id', $moduleId);
            }

            if (!empty($searchTerm)) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', '%' . $searchTerm . '%')
                        ->orWhere('description', 'like', '%' . $searchTerm . '%');
                });
            }

            $query->orderByDesc("created_at");

            $sections = $query->paginate($perPage, ['*'], 'page', $page);
            $courseId = $sections->first()->module->course_id;

            $sections->getCollection()->transform(function (CourseSection $section) {
                return [
                    'id' => $section->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'materials' => (int) $section->materials_count,
                ];
            });
            return response()->json([
                "success" => true,
                "message" => "Sections retrieved successfully",
                "sections" => $sections,
                'course_id' => $courseId,
            ]);
        } catch (\Exception $e) {
            \Log::error("Error retrieving sections", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to retrieve sections. Please try again later.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function deleteSection(int $sectionId): JsonResponse
    {
        try {
            $section = CourseSection::find($sectionId);

            if (!$section) {
                return response()->json([
                    "success" => false,
                    "message" => "Section not found.",
                ], 404);
            }

            $module = $section->module;

            DB::transaction(function () use ($section, $module) {
                $sectionDuration = $section->duration ?? 0;
                $sectionFiles = $section->nr_of_files ?? 0;

                $section->delete();

                if ($module) {
                    $module->duration = max(0, ($module->duration ?? 0) - $sectionDuration);
                    $module->nr_of_files = max(0, ($module->nr_of_files ?? 0) - $sectionFiles);
                    $module->save();

                    $course = $module->course;
                    if ($course) {
                        $course->duration = max(0, ($course->duration ?? 0) - $sectionDuration);
                        $course->nr_of_files = max(0, ($course->nr_of_files ?? 0) - $sectionFiles);
                        $course->save();
                    }
                }
            });

            return response()->json([
                "success" => true,
                "message" => "Section deleted successfully.",
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error deleting section", [
                'section_id' => $sectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to delete section. Please try again later.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function createSection(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                "description" => "required|string",
                "module_id" => "required|integer|exists:course_modules,id"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $section = DB::transaction(function () use ($request) {
                return CourseSection::create([
                    'module_id' => $request->input("module_id"),
                    'title' => $request->input("title"),
                    'description' => $request->input("description"),
                    'nr_of_files' => 0,
                    'duration' => 0,
                ]);
            });

            return response()->json([
                "success" => true,
                "message" => "Section created successfully.",
                "module" => [
                    "id" => $section->id,
                    "title" => $section->title,
                    "description" => $section->description,
                    "materials" => 0
                ]
            ], 201);
        } catch (\Exception $e) {
            \Log::error("Error creating section", [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to create section. Please try again later.",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function getSectionById(int $sectionId): JsonResponse
    {
        try {
            $section = CourseSection::find($sectionId);

            if (!$section) {
                return response()->json([
                    "success" => false,
                    "message" => "Section not found.",
                ], 404);
            }

            return response()->json([
                "success" => true,
                "message" => "Section retrieved successfully.",
                "section" => [
                    "id" => $section->id,
                    "title" => $section->title,
                    "description" => $section->description,
                    "module_id" => $section->module_id,
                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error retrieving section", [
                'section_id' => $sectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),

            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to retrieve section. Please try again later.",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function updateSection(Request $request, int $sectionId): JsonResponse
    {
        try {
            $section = CourseSection::find($sectionId);

            if (!$section) {
                return response()->json([
                    "success" => false,
                    "message" => "Section not found.",
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

            DB::transaction(function () use ($section, $request) {
                $section->title = $request->input("title");
                $section->description = $request->input("description");
                $section->save();
            });

            $section->refresh();
            $section->load('materials');

            return response()->json([
                "success" => true,
                "message" => "Section updated successfully.",
                "section" => [
                    "id" => $section->id,
                    "title" => $section->title,
                    "description" => $section->description,
                    "materials" => (int) $section->materials_count,
                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error updating section", [
                'section_id' => $sectionId,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),

            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to update section. Please try again later.",
                "error" => $e->getMessage(),
            ], 500);
        }
    }
}
