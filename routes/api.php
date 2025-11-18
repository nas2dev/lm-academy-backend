<?php

use App\Http\Controllers\ChunkUploadController;
use App\Http\Controllers\CourseController;
use App\Mail\TestMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::controller(AuthController::class)->prefix('auth')->middleware('api')->group(function () {
    Route::post('login', 'login')->name('auth.login');
    Route::post('refresh', 'refresh')->name('auth.refresh');

    Route::post('forgot-password', 'forgotPassword')->name("auth.forgotPassword");
    Route::post("verify-reset-token", "verifyPasswordResetToken")->name("auth.verifyPasswordResetToken");
    Route::post("reset-password", "resetPassword")->name("auth.resetPassword");

    // Auth routes
    Route::middleware('jwt.auth.token')->group(function () {
        Route::post('logout', 'logout')->name('auth.logout');
        Route::get('user-profile', 'userProfile')->name('auth.userProfile');
        Route::post('send-registration-invite', 'sendRegistrationInvite')->name('auth.sendRegistrationInvite');
        Route::post("complete-profile", "completeProfile")->name("auth.completeProfile");
    });
    Route::post('verify-registration-token', 'verifyRegistrationToken')->name('auth.verifyRegistrationToken');
    Route::post("register", "register")->name("auth.register");
});

Route::controller(UserController::class)->prefix('users')->middleware(['api', 'jwt.auth.token'])->group(function () {
    Route::middleware('role:Admin')->group(function () {
        Route::get('all-users', 'allUsers')->name('users.allUsers');
        Route::post('change-role', "changeUserRole")->name("users.changeUserRole");
        Route::post('change-status', "changeAccountStatus")->name("users.changeAccountStatus");
    });

    Route::get('/{id}/profile', 'getUserProfileById')->name('users.getUserProfileById');
    Route::put('update-profile', 'updateProfile')->name('users.updateProfile');
    Route::post('profile/image', 'uploadProfileImage')->name('users.uploadProfileImage');
    Route::delete('profile/image', 'deleteProfileImage')->name('users.deleteProfileImage');

    Route::post('change-password', 'changePassword')->name('users.changePassword');
});

Route::controller(CourseController::class)->prefix('courses')->middleware(['api', 'jwt.auth.token'])->group(function () {
    Route::middleware('role:Admin')->group(function () {
        Route::get('/', 'getAllCourses')->name('courses.getAllCourses');
        Route::post("/", "createCourse")->name("courses.createCourse");
        Route::get('/{courseId}', 'getCourseById')->name('courses.getCourseById');
        Route::delete('/{courseId}', 'deleteCourse')->name('courses.deleteCourse');
        Route::post("change-status", "changeCourseStatus")->name("courses.changeCourseStatus");
        Route::put('/{courseId}', 'updateCourse')->name('courses.updateCourse');
        Route::delete('/{courseId}/video', 'deleteCourseVideo')->name('courses.deleteCourseVideo');
    });
});

Route::controller(ChunkUploadController::class)->prefix('chunks')->middleware(['api', 'throttle:1000,1', 'jwt.auth.token'])->group(function () {
    Route::post('/upload/course-video', 'uploadCourseVideo')->name('chunk.uploadCourseVideo');
});

Route::post("test-mail-send", function () {
    $data = [
        "title" => "Test Mail FROM ROUTE",
        "message" => "This is a test mail"
    ];

    // here we need to send an actual email
    Mail::to("nas2dev@gmail.com")->send(new TestMail($data));
    return response()->json([
        "message" => "Mail sent successfully"
    ]);
});

Route::get('zen-quote', function () {
    try {
        $response = Http::get("https://zenquotes.io/api/random");

        if ($response->successful()) {
            $quote = $response->json()[0];
            return response()->json([
                'success' => true,
                "quote" => [
                    "text" => $quote['q'],
                    "author" => $quote['a']
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            "message" => "Failed to fetch quote from external API"
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            "message" => "Failed to fetch quote from external API",
            "error" => [
                "message" => "Failed to fetch quote",
                "details" => $e->getMessage()
            ]
        ]);
    }
});