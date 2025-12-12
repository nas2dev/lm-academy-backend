<?php

namespace App\Http\Controllers;

use Validator;
use App\Models\Course;
use App\Models\Scoreboard;
use Illuminate\Support\Str;
use App\Models\CourseModule;
use Illuminate\Http\Request;
use App\Models\CourseSection;
use App\Models\CourseMaterial;
use Illuminate\Http\JsonResponse;
use App\Models\UserCourseProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\UserCourseSectionProgress;

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

    public function getMaterialById(int $materialId): JsonResponse
    {
        try {
            $material = CourseMaterial::with([
                'creator:id,first_name,last_name',
                'updator:id,first_name,last_name'
            ])->find($materialId);

            if (!$material) {
                return response()->json([
                    'success' => false,
                    'message' => 'Material not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Course material retrieved successfully.',
                'material' => [
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
                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error getting course material by id', [
                'material_id' => $materialId,
                'user_id' => request()->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get course material by id. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateSortOrder(Request $request, int $sectionId): JsonResponse
    {
        try {
            $section = CourseSection::find($sectionId);

            if (empty($section)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found.',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'materials' => 'required|array',
                'materials.*.id' => 'required|exists:course_materials,id',
                'materials.*.sort_order' => 'required|integer|min:0',
            ], [
                'materials.required' => 'The materials array is required.',
                'materials.*.id.required' => 'The material id is required.',
                'materials.*.id.exists' => 'This material does not exist.',
                'materials.*.sort_order.required' => 'The sort order is required.',
                'materials.*.sort_order.integer' => 'The sort order must be an integer.',
                'materials.*.sort_order.min' => 'The sort order must be at least 0.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $materials = $request->input('materials');

            DB::transaction(function () use ($materials, $sectionId) {
                foreach ($materials as $material) {
                    // Verify the material belongs to this section
                    CourseMaterial::where('id', $material['id'])->where('course_section_id', $sectionId)
                        ->update(['sort_order' => $material['sort_order']]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Materials sort order updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error updating materials sort order', [
                'section_id' => $sectionId,
                'user_id' => request()->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update materials sort order. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // user routes

    public function getSectionDetailsForUser(Request $request, int $sectionId): JsonResponse
    {
        try {
            $user = $request->user();

            // check if user is Admin - restrict access
            if ($user->hasRole("Admin")) {
                return response()->json([
                    'success' => false,
                    'message' => 'This endpoint is not accessible to admins.',
                ], 403);
            }

            // Fetch sections with module and course

            $section = CourseSection::with([
                'module:id,course_id,title,description,duration',
                'module.course:id,title,duration',
            ])->find($sectionId);

            if (!$section) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found.',
                ], 404);
            }

            // check if user is enrolled in the course
            $userProgress = UserCourseProgress::where('user_id', $user->id)
                ->where('course_id', $section->module->course_id)
                ->first();

            if (!$userProgress) {
                return response()->json([
                    "success" => false,
                    "message" => "You are not enrolled in this course.",
                ], 404);
            }

            // check if the section is completed for auth user
            $sectionCompleted = UserCourseSectionProgress::where('user_id', $user->id)
                ->where('course_section_id', $sectionId)
                ->exists();

            // Get course Materias for this section
            $courseMaterials = CourseMaterial::with([
                'creator:id,first_name,last_name',
                'updator:id,first_name,last_name'
            ])
                ->where('course_section_id', $sectionId)
                ->orderBy('sort_order', 'asc')
                ->get()
                ->map(function (CourseMaterial $material) {
                    return [
                        'id' => $material->id,
                        'title' => $material->title,
                        "type" => $material->type,
                        "content" => $material->content,
                        "material_url" => $material->material_url,
                        "sort_order" => $material->sort_order,
                        "created_by" => $material->creator
                            ? trim(($material->creator->first_name ?? '') . ' ' . ($material->creator->last_name ?? ''))
                            : null,
                        "updated_by" => $material->updator
                            ? trim(($material->updator->first_name ?? '') . ' ' . ($material->updator->last_name ?? ''))
                            : null,
                        "created_at" => optional($material->created_at)->toIso8601String(),
                        "updated_at" => optional($material->updated_at)->toIso8601String(),
                    ];
                });

            $nextStep = $this->getNextStep($section);
            $previousStep = $this->getPreviousStep($section);

            $courseCompleted = $userProgress->pending_sections == 0 && $userProgress->pending_modules == 0;

            return response()->json([
                'success' => true,
                'message' => "Section details retrieved successfully.",
                "section" => [
                    "id" => $section->id,
                    "title" => $section->title,
                    "description" => $section->description,
                    "duration" => $section->duration,
                    "nr_of_files" => $section->nr_of_files,
                ],
                "module" => $section->module ? [
                    "id" => $section->module->id,
                    "title" => $section->module->title,
                    "description" => $section->module->description,
                    "duration" => $section->module->duration,
                ] : null,
                "course" => $section->module && $section->module->course ? [
                    "id" => $section->module->course->id,
                    "title" => $section->module->course->title,
                    "duration" => $section->module->course->duration,
                ] : null,
                'courseMaterials' => $courseMaterials,
                'next_step' => $nextStep,
                'previous_step' => $previousStep,
                'section_completed' => $sectionCompleted,
                'course_completed' => $courseCompleted,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error retrieving section details for user', [
                'section_id' => $sectionId,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve section details for user. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function getNextStep($currentSection)
    {
        // check if module exists
        if (!$currentSection->module) {
            return [
                'type' => 'course_end',
                'section_id' => null,
            ];
        }

        $nextSection = CourseSection::where('module_id', $currentSection->module_id)
            ->where('id', '>', $currentSection->id)
            ->orderBy('id', 'asc')
            ->first();

        if ($nextSection) {
            return [
                'type' => 'next_section',
                'section_id' => $nextSection->id,
            ];
        }

        $nextModule = CourseModule::where('course_id', $currentSection->module->course_id)
            ->where('id', '>', $currentSection->module->id)
            ->orderBy('id', 'asc')
            ->first();

        if ($nextModule) {
            $firstSectionOfNextModule = CourseSection::where('module_id', $nextModule->id)
                ->orderBy('id', 'asc')
                ->first();

            if ($firstSectionOfNextModule) {
                return [
                    'type' => 'next_module',
                    'section_id' => $firstSectionOfNextModule->id,
                ];
            }

        }

        return [
            'type' => 'course_end',
            'section_id' => null,
        ];
    }

    private function getPreviousStep($currentSection)
    {
        // check if module exists
        if (!$currentSection->module) {
            return [
                'type' => 'course_start',
                'section_id' => null,
            ];
        }

        $previousSection = CourseSection::where('module_id', $currentSection->module_id)
            ->where('id', '<', $currentSection->id)
            ->orderBy('id', 'desc')
            ->first();

        if ($previousSection) {
            return [
                'type' => 'previous_section',
                'section_id' => $previousSection->id,
            ];
        }

        $previousModule = CourseModule::where('course_id', $currentSection->module->course_id)
            ->where('id', '<', $currentSection->module->id)
            ->orderBy('id', 'desc')
            ->first();

        if ($previousModule) {
            $lastSectionOfPreviousModule = CourseSection::where('module_id', $previousModule->id)
                ->orderBy('id', 'desc')
                ->first();

            if ($lastSectionOfPreviousModule) {
                return [
                    'type' => 'previous_module',
                    'section_id' => $lastSectionOfPreviousModule->id,
                ];
            }

        }

        return [
            'type' => 'course_start',
            'section_id' => null,
        ];
    }

    public function markSectionDone(Request $request, int $sectionId): JsonResponse
    {
        try {
            DB::beginTransaction();
            $user = $request->user();

            $section = CourseSection::with([
                'module:id,course_id,title,description,duration',
                'module.course:id,title,duration',
                'materials'
            ])->find($sectionId);

            if (!$section) {
                return response()->json([
                    "success" => false,
                    "message" => "Section not found",
                ], 404);
            }

            $userProgress = UserCourseProgress::where('user_id', $user->id)
                ->where('course_id', $section->module->course_id)
                ->first();

            if (!$userProgress) {
                return response()->json([
                    "success" => false,
                    "message" => "You are not enrolled in this course",
                ], 404);
            }

            $userSectionProgress = UserCourseSectionProgress::where('user_id', $user->id)
                ->where('course_section_id', $sectionId)
                ->first();

            if ($userSectionProgress) {
                return response()->json([
                    "success" => false,
                    "message" => "This section is already marked as completed",
                ], 409);
            }

            if ($section->materials->isEmpty()) {
                return response()->json([
                    "success" => false,
                    "message" => "Cannot complete this section as it has no materials.",
                ], 400);
            }

            UserCourseSectionProgress::create([
                "user_id" => $user->id,
                "course_section_id" => $sectionId
            ]);

            // Ensure completedSectionIds is an array
            $completedSectionIds = is_array($userProgress->completed_section_ids) ? $userProgress->completed_section_ids : [];

            // Check if the section ID is already in the completedSectionIds array
            if (!in_array($sectionId, $completedSectionIds)) {
                $completedSectionIds[] = $section->id; // Add the completed section ID
            }

            // Update the user progress with the new completed section IDs
            $userProgress->completed_section_ids = $completedSectionIds;
            $userProgress->completed_sections++; // Increase completed sections
            $userProgress->pending_sections = max(0, $userProgress->pending_sections - 1); // Decrease pending sections
            $userProgress->save();

            $moduleId = $section->module_id;
            $allSectionIds = CourseSection::where('module_id', $moduleId)->pluck('id')->toArray(); // Get all section IDs for the module
            $completedModuleSections = array_intersect($allSectionIds, $completedSectionIds); // Check completed sections

            if (count($completedModuleSections) === count($allSectionIds)) {
                // If all sections are completed
                $completed_module_ids = is_array($userProgress->completed_module_ids) ? $userProgress->completed_module_ids : [];

                if (!in_array($moduleId, $completed_module_ids)) {
                    $completed_module_ids[] = $moduleId; // Add the completed module ID
                }

                $userProgress->completed_module_ids = $completed_module_ids;
                $userProgress->completed_modules++; // Increase completed modules
                $userProgress->pending_modules = max(0, $userProgress->pending_modules - 1); // Decrease pending modules
                $userProgress->save();
            }

            // Check if course is completed after section completion
            if ($userProgress->pending_sections == 0 && $userProgress->pending_modules == 0 && !$userProgress->awarded) {
                $userProgress->awarded = true;

                // Award points for course completion based on module count
                $moduleCount = CourseModule::where('course_id', $section->module->course_id)->count();

                $points = match (true) {
                    $moduleCount == 1 => 100,
                    $moduleCount == 2 => 111,
                    $moduleCount == 3, $moduleCount == 4 => 123,
                    $moduleCount == 5 => 155,
                    $moduleCount > 5 => 199,
                    default => 0,
                };

                $scoreboard = Scoreboard::firstOrCreate(
                    ['user_id' => $user->id],
                    ['score' => 0]
                );

                $scoreboard->score += $points;
                $scoreboard->save();

                $userProgress->save();
            }

            DB::commit();

            return response()->json([
                "success" => true,
                "message" => "Section marked as completed successfully",
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error("Error marking section as done", [
                'section_id' => $sectionId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark section as done. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function markSectionUndone(Request $request, int $sectionId): JsonResponse
    {
        try {
            DB::beginTransaction();
            $user = $request->user();

            $section = CourseSection::with([
                'module:id,course_id,title,description,duration',
                'module.course:id,title,duration',
            ])->find($sectionId);

            if (!$section) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found.',
                ], 404);
            }

            $userProgress = UserCourseProgress::where('user_id', $user->id)
                ->where('course_id', $section->module->course_id)
                ->first();

            if (!$userProgress) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not enrolled in this course.',
                ], 404);
            }

            // Delete the entry from user_course_section_progress table
            UserCourseSectionProgress::where('user_id', $user->id)
                ->where('course_section_id', $sectionId)
                ->delete();

            // Ensure completedSectionIds is an array
            $completedSectionIds = is_array($userProgress->completed_section_ids) ? $userProgress->completed_section_ids : [];

            // Remove the section ID from the completedSectionIds array
            if (in_array($sectionId, $completedSectionIds)) {
                $completedSectionIds = array_values(array_diff($completedSectionIds, [$sectionId])); // Remove the section ID and reindex
                // Update the user progress
                $userProgress->completed_section_ids = $completedSectionIds;
                $userProgress->completed_sections = max(0, $userProgress->completed_sections - 1); // Decrease completed sections
                $userProgress->pending_sections++; // Increase pending sections
                $userProgress->save();
            }

            $moduleId = $section->module_id;
            $completed_module_ids = is_array($userProgress->completed_module_ids) ? $userProgress->completed_module_ids : [];

            // Remove moduleId if it exists from module_ids
            if (in_array($moduleId, $completed_module_ids)) {
                // Check if the user has lost the module completion
                $allSectionIds = CourseSection::where('module_id', $moduleId)->pluck('id')->toArray();
                $completedModuleSections = array_intersect($allSectionIds, $completedSectionIds); // Check completed sections

                // If the user has lost the module completion
                if (count($completedModuleSections) < count($allSectionIds)) {
                    // Remove the module ID from completed_module_ids
                    $completed_module_ids = array_values(array_diff($completed_module_ids, [$moduleId])); // Remove the module ID and reindex

                    $userProgress->completed_module_ids = $completed_module_ids;
                    $userProgress->completed_modules = max(0, $userProgress->completed_modules - 1); // Decrease completed modules
                    $userProgress->pending_modules++; // Increase pending modules
                    $userProgress->save();
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Section marked as incomplete successfully.',
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error marking section as incomplete", [
                'section_id' => $sectionId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark section as incomplete. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
