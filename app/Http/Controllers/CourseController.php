<?php

namespace App\Http\Controllers;

use Log;
use Validator;
use App\Models\Course;
use Illuminate\Http\Request;

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
}
