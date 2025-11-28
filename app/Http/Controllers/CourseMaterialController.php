<?php

namespace App\Http\Controllers;

use Validator;
use App\Models\Course;
use Illuminate\Support\Str;
use App\Models\CourseModule;
use Illuminate\Http\Request;
use App\Models\CourseSection;
use App\Models\CourseMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CourseMaterialController extends Controller
{
    public function getCourseMaterialsBySectionId(int $sectionId): JsonResponse
    {
        try {
            $section = CourseSection::with([
                'module:id,course_id,title,description,duration',
                'module.course'
            ])->findOrFail($sectionId);

            if (!$section) {
                return response()->json([
                    "success" => false,
                    "message" => "Section not found"
                ], 404);
            }

            $materials = CourseMaterial::with([
                'creator:id,first_name,last_name',
                'updator:id,first_name,last_name'
            ])
                ->where('course_section_id', $sectionId)
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get()
                ->map(function (CourseMaterial $material) {
                    return [
                        'id' => $material->id,
                        'title' => $material->title,
                        'type' => $material->type,
                        'content' => $material->content,
                        'material_url' => $material->material_url,
                        'sort_order' => $material->sort_order,
                        'created_by' => $material->creator ? trim(($material->creator->first_name ?? '') . ' ' . ($material->creator->last_name ?? '')) : null,
                        'updated_by' => $material->updator ? trim(($material->updator->first_name ?? '') . ' ' . ($material->updator->last_name ?? '')) : null,
                        'created_at' => optional($material->created_at)->format('d.m.Y H:i'),
                        'updated_at' => optional($material->updated_at)->format('d.m.Y H:i'),
                    ];
                });

            return response()->json([
                "success" => true,
                "message" => "Course materials retrieved successfully",
                'section' => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'description' => $section->description,
                ],
                'module' => [
                    'id' => $section->module->id,
                    'title' => $section->module->title,
                    'description' => $section->module->description,
                    'duration' => $section->module->duration,
                ],
                'course' => [
                    'id' => $section->module->course->id,
                    'title' => $section->module->course->title,
                ],
                'materials' => $materials,
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error retrieving materials", [
                'section_id' => $sectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                "success" => false,
                "message" => "Failed to retrieve materials. Please try again later.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function createMaterial(Request $request): JsonResponse
    {
        try {
            $sectionId = $request->section_id;

            $section = CourseSection::with(['module:id,course_id,title'])
                ->find($sectionId);

            if (empty($section)) {
                return response()->json([
                    "success" => false,
                    "message" => "Section not found"
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'type' => 'required|string|in:text,image,file',
                'content' => 'nullable|string',
                'material' => 'required_if:type,image,file|file',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $course = Course::find($section->module->course_id);

            $materialUrl = null;
            $user = $request->user();

            // Additional validation and file handling based on the type
            if ($request->hasFile('material') && $request->type !== 'text') {
                $material = $request->file('material');

                if ($request->type === 'image') {
                    $imageValidator = Validator::make($request->all(), [
                        'material' => 'mimes:jpeg,png,jpg,gif,webp, svg|max:5120' // 5MB
                    ]);

                    if ($imageValidator->fails()) {
                        return response()->json([
                            "success" => false,
                            "message" => "Validation failed",
                            "errors" => $imageValidator->errors()
                        ], 422);
                    }

                    $fileName = time() . '_' . Str::random(10) . '.' . $material->getClientOriginalExtension();
                    $materialUrl = $material->storeAs('course-materials', $fileName, 'public');
                } else if ($request->type === 'file') {
                    $fileValidator = Validator::make($request->all(), [
                        'material' => 'mimes:pdf,doc,docx,xls,xlsx|max:10240' // 10MB
                    ]);

                    if ($fileValidator->fails()) {
                        return response()->json([
                            "success" => false,
                            "message" => "Validation failed",
                            "errors" => $fileValidator->errors()
                        ], 422);
                    }

                    $fileName = time() . '_' . Str::random(10) . '.' . $material->getClientOriginalExtension();
                    $materialUrl = $material->storeAs('course-materials', $fileName, 'public');
                }

                // Increment nr_of_files for section, module and course
                $section->increment('nr_of_files');
                $module = CourseModule::find($section->module_id);
                $module->increment('nr_of_files');
                $course->increment('nr_of_files');
            }

            // Calculate sort_order (max + 1)
            $maxSortOrder = CourseMaterial::where('course_section_id', $sectionId)->max('sort_order');
            $sortOrder = $maxSortOrder ? $maxSortOrder + 1 : 1;

            $courseMaterial = DB::transaction(function () use ($request, $section, $materialUrl, $sortOrder, $user) {
                return CourseMaterial::create([
                    'course_section_id' => $section->id,
                    'title' => $request->input('title'),
                    'type' => $request->input('type'),
                    'content' => $request->input('content'),
                    'material_url' => $materialUrl,
                    'sort_order' => $sortOrder,
                    'created_by' => $user->id,
                ]);
            });

            return response()->json([
                "success" => true,
                "message" => "Course material created successfully",
                "course_material" => [
                    "id" => $courseMaterial->id,
                    "title" => $courseMaterial->title,
                    "type" => $courseMaterial->type,
                    "content" => $courseMaterial->content,
                    "material_url" => $courseMaterial->material_url,
                    "sort_order" => $courseMaterial->sort_order,
                ]
            ], 201);
        } catch (\Exception $e) {
            // Clean up uploaded file if it exists
            if (isset($materialUrl) && Storage::disk('public')->exists($materialUrl)) {
                Storage::disk('public')->delete($materialUrl);
            }
            \Log::error("Error creating material", [
                'user_id' => $user->id,
                'section_id' => $sectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                "success" => false,
                "message" => "Failed to create material. Please try again later.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function deleteMaterial(int $materialId): JsonResponse
    {
        try {
            $courseMaterial = CourseMaterial::with(['section.module.course'])
                ->find($materialId);

            if (empty($courseMaterial)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Material not found.',
                ], 404);
            }

            $section = $courseMaterial->section;
            $module = $section->module;
            $course = $module->course;

            DB::transaction(function () use ($courseMaterial, $section, $module, $course) {
                $duration = 0;
                $hasFile = false;

                // Handle video type - calculate duration before deletion
                if ($courseMaterial->type === 'video' && $courseMaterial->material_url) {
                    $hasFile = true;
                    if (Storage::disk('public')->exists($courseMaterial->material_url)) {
                        $getID3 = new \getID3();
                        $videoPath = Storage::disk('public')->path($courseMaterial->material_url);
                        $fileInfo = $getID3->analyze($videoPath);

                        if (isset($fileInfo['playtime_seconds'])) {
                            $duration = floor($fileInfo['playtime_seconds']);
                        }

                        // Decrement duration for section, module, and course
                        $section->duration = max(0, $section->duration - $duration);
                        $module->duration = max(0, $module->duration - $duration);
                        $course->duration = max(0, $course->duration - $duration);
                    }
                } elseif ($courseMaterial->type === 'image' || $courseMaterial->type === 'file') {
                    // Image and file types have files
                    $hasFile = true;
                }

                // Decrement nr_of_files only if material has a file (not text type)
                if ($hasFile) {
                    $section->nr_of_files = max(0, $section->nr_of_files - 1);
                    $section->save();

                    $module->nr_of_files = max(0, $module->nr_of_files - 1);
                    $module->save();

                    $course->nr_of_files = max(0, $course->nr_of_files - 1);
                    $course->save();
                }

                // Delete the file from storage if it exists
                if (!empty($courseMaterial->material_url)) {
                    if (Storage::disk('public')->exists($courseMaterial->material_url)) {
                        Storage::disk('public')->delete($courseMaterial->material_url);
                    }
                }

                // Delete the material record
                $courseMaterial->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Course material deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error deleting course material', [
                'material_id' => $materialId,
                'user_id' => request()->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete course material. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateMaterial(Request $request, int $materialId): JsonResponse
    {
        try {
            $courseMaterial = CourseMaterial::find($materialId);

            if (empty($courseMaterial)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Material not found.',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'nullable|string',
                'material' => 'nullable|file'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $materialUrl = $courseMaterial->material_url;
            $user = $request->user();

            // Handle file replacement for image and file types
            if ($request->hasFile('material') && ($courseMaterial->type === 'image' || $courseMaterial->type === 'file')) {
                $material = $request->file('material');

                if ($courseMaterial->type === 'image') {
                    $imageValidator = Validator::make($request->all(), [
                        'material' => 'mimes:jpeg,png,jpg,svg,webp|max:4096',
                    ]);

                    if ($imageValidator->fails()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => $imageValidator->errors(),
                        ], 422);
                    }

                    $fileName = time() . '_' . Str::random(10) . '.' . $material->getClientOriginalExtension();
                    $materialUrl = $material->storeAs('course-materials', $fileName, 'public');
                } elseif ($courseMaterial->type === 'file') {
                    $fileValidator = Validator::make($request->all(), [
                        'material' => 'mimes:pdf,doc,docx,xls,xlsx|max:10240', // 10MB limit
                    ]);

                    if ($fileValidator->fails()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => $fileValidator->errors(),
                        ], 422);
                    }

                    $fileName = time() . '_' . Str::random(10) . '.' . $material->getClientOriginalExtension();
                    $materialUrl = $material->storeAs('course-materials', $fileName, 'public');
                }

                // Delete old file if it exists
                if (!empty($courseMaterial->material_url)) {
                    if (Storage::disk('public')->exists($courseMaterial->material_url)) {
                        Storage::disk('public')->delete($courseMaterial->material_url);
                    }
                }
            }

            DB::transaction(function () use ($request, $courseMaterial, $materialUrl, $user) {
                $updateData = [
                    'title' => $request->input('title'),
                    'updated_by' => $user->id
                ];

                if ($courseMaterial->type === 'text') {
                    $updateData['content'] = $request->input('content');
                } elseif ($request->hasFile('material') && ($courseMaterial->type === 'image' || $courseMaterial->type === 'file')) {
                    $updateData['material_url'] = $materialUrl;
                }

                $courseMaterial->update($updateData);
            });

            return response()->json([
                'success' => true,
                'message' => 'Course material updated successfully.',
                'material' => [
                    'id' => $courseMaterial->id,
                    'title' => $courseMaterial->title,
                    'type' => $courseMaterial->type,
                    'content' => $courseMaterial->content,
                    'material_url' => $courseMaterial->material_url,
                ]
            ]);
        } catch (\Exception $e) {
            // Clean up uploaded file if it exists
            if (isset($materialUrl) && $materialUrl !== $courseMaterial->material_url && Storage::disk('public')->exists($materialUrl)) {
                Storage::disk('public')->delete($materialUrl);
            }

            \Log::error('Error updating course material', [
                'material_id' => $materialId,
                'user_id' => request()->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update course material. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
