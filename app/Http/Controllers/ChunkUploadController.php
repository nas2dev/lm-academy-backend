<?php

namespace App\Http\Controllers;

use Validator;
use App\Models\Course;
use Illuminate\Support\Str;
use App\Models\CourseModule;
use Illuminate\Http\Request;
use App\Models\CourseSection;
use App\Models\CourseMaterial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;

class ChunkUploadController extends Controller
{
    public function uploadCourseVideo(Request $request): mixed
    {
        $path = null;
        try {
            $user = $request->user();

            if (!$user || !$user->hasRole("Admin")) {
                return response()->json([
                    "success" => false,
                    "message" => "Unauthorized access."
                ], 403);
            }

            $course = Course::find($request->course_id);
            if (empty($course)) {
                return response()->json([
                    "success" => false,
                    "message" => "Course not found."
                ], 404);
            }

            // Extract file extension from the resumableFilename object
            $fileExtension = strtolower(pathinfo($request->input("resumableFilename"), PATHINFO_EXTENSION));

            // Manually validate the file extension
            $validator = Validator::make($request->all(), [
                'file' => 'required',
                'resumableFilename' => [
                    function ($attribute, $value, $fail) use ($fileExtension) {
                        $allowedExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
                        if (!in_array($fileExtension, $allowedExtensions)) {
                            $fail("The file must be one of the following extensions: " . implode(", ", $allowedExtensions));
                        }
                    }
                ],
                'resumableTotalSize' => 'required|numeric|max:314572800' // 300MB limit
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $receiver = new FileReceiver('file', $request, HandlerFactory::classFromRequest($request));

            if ($receiver->isUploaded() === false) {
                throw new UploadMissingFileException();
            }

            $save = $receiver->receive();

            if ($save->isFinished()) {
                $file = $save->getFile();
                $path = $this->saveFileToStorage($file, 'videos/courses');

                // Initialize GetID3
                $getID3 = new \getID3();

                // check and remove old file
                if (!empty($course->intro_video_url)) {
                    if (Storage::disk('public')->exists($course->intro_video_url)) {
                        $course->nr_of_files = max(0, $course->nr_of_files - 1);

                        // Get full path for getID3 analysis
                        $videoPath = Storage::disk('public')->path($course->intro_video_url);
                        $fileInfo = $getID3->analyze($videoPath);
                        if (isset($fileInfo['playtime_seconds'])) {
                            $oldDuration = floor($fileInfo['playtime_seconds']);
                            $course->duration = max(0, $course->duration - $oldDuration);
                        }

                        $course->save();

                        // Delete using Storage facade
                        Storage::disk('public')->delete($course->intro_video_url);
                    }
                }

                // Analyze new file - get full path for getID3 analysis
                $newDuration = 0;
                $fullPath = Storage::disk('public')->path($path);
                $fileInfo = $getID3->analyze($fullPath);

                if (isset($fileInfo['playtime_seconds'])) {
                    $newDuration = floor($fileInfo['playtime_seconds']);
                }

                $course->update([
                    'intro_video_url' => $path,
                    'nr_of_files' => $course->nr_of_files + 1,
                    'duration' => $course->duration + $newDuration,
                ]);

                return response()->json([
                    "status" => true,
                    "path" => $path,
                ]);
            }

            return response()->json([
                "status" => true,
                "progress" => $save->handler()->getPercentageDone()
            ]);
        } catch (\Exception $e) {
            // Clean up the uploaded fie if it exists
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            Log::error("Error uploading course video", [
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error uploading course video"
            ], 500);
        }
    }

    public function uploadCourseVideoMaterial(Request $request): mixed
    {
        $path = null;
        try {
            $user = $request->user();

            if (!$user || !$user->hasRole("Admin")) {
                return response()->json([
                    "success" => false,
                    "message" => "Unauthorized access."
                ], 403);
            }

            $sectionId = $request->section_id;
            $section = CourseSection::with(['module:id,course_id,title'])->find($sectionId);
            if (empty($section)) {
                return response()->json([
                    "success" => false,
                    "message" => "Course section not found."
                ], 404);
            }

            // Extract file extension from the resumableFilename object
            $fileExtension = strtolower(pathinfo($request->input("resumableFilename"), PATHINFO_EXTENSION));

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'file' => 'required',
                'resumableFilename' => [
                    function ($attribute, $value, $fail) use ($fileExtension) {
                        $allowedExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
                        if (!in_array($fileExtension, $allowedExtensions)) {
                            $fail("The file must be one of the following extensions: " . implode(", ", $allowedExtensions));
                        }
                    }
                ],
                'resumableTotalSize' => 'required|numeric|max:1073741824' // 1GB limit
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $validator->errors()
                ], 422);
            }

            $receiver = new FileReceiver('file', $request, HandlerFactory::classFromRequest($request));

            if ($receiver->isUploaded() === false) {
                throw new UploadMissingFileException();
            }

            $save = $receiver->receive();

            if ($save->isFinished()) {
                $file = $save->getFile();
                $path = $this->saveFileToStorage($file, 'course-materials');

                // Initialize GetID3
                $getID3 = new \getID3();

                // Analyze new file - get full path for getID3
                $duration = 0;
                $fullPath = Storage::disk('public')->path($path);
                $fileInfo = $getID3->analyze($fullPath);

                if (isset($fileInfo['playtime_seconds'])) {
                    $duration = floor($fileInfo['playtime_seconds']);
                }

                $user = $request->user();

                DB::transaction(function () use ($request, $section, $path, $duration, $user) {
                    // Calculate sort_order (max + 1)
                    $maxSortOrder = CourseMaterial::where('course_section_id', $section->id)->max('sort_order');
                    $sortOrder = $maxSortOrder ? $maxSortOrder + 1 : 1;

                    // Create the material
                    $courseMaterial = CourseMaterial::create([
                        'title' => $request->title,
                        'type' => 'video',
                        'material_url' => $path,
                        'sort_order' => $sortOrder,
                        'created_by' => $user->id,
                        'course_section_id' => $section->id,
                    ]);

                    // Increment nr_of_files and duration for section
                    $section->increment('nr_of_files');
                    $section->increment('duration', $duration);

                    // Increment nr_of_files and duration for module
                    $module = CourseModule::find($section->module_id);
                    if ($module) {
                        $module->increment('nr_of_files');
                        $module->increment('duration', $duration);
                    }

                    // Increment nr_of_files and duration for course
                    $course = Course::find($section->module->course_id);
                    if ($course) {
                        $course->increment('nr_of_files');
                        $course->increment('duration', $duration);
                    }
                });

                return response()->json([
                    'status' => true,
                    'path' => $path,
                    'message' => 'Video material uploaded successfully',
                ]);
            }

            return response()->json([
                'status' => true,
                'progress' => $save->handler()->getPercentageDone(),
            ]);
        } catch (\Throwable $e) {
            // Clean up the uploaded fie if it exists
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            Log::error("Error uploading course video material", [
                'section_id' => $section->id,
                'user_id' => $user->id,
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Error uploading course video material"
            ], 500);
        }
    }

    protected function saveFileToStorage($file, $folder): mixed
    {
        $fileName = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $fileName, 'public');

        return $path;
    }
}
