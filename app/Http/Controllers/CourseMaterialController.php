<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CourseSection;
use App\Models\CourseMaterial;
use Illuminate\Http\JsonResponse;

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
                        'created_at' => optional($material->created_at)->format('d.m.Y H:i:s'),
                        'updated_at' => optional($material->updated_at)->format('d.m.Y H:i:s'),
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
}
