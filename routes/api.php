<?php

use App\Mail\TestMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::controller(AuthController::class)->prefix('auth')->middleware('api')->group(function () {
    Route::post('login', 'login')->name('auth.login');
    Route::post('refresh', 'refresh')->name('auth.refresh');

    // Auth routes
    Route::middleware('jwt.auth.token')->group(function () {
        Route::post('logout', 'logout')->name('auth.logout');
        Route::get('user-profile', 'userProfile')->name('auth.userProfile');
        Route::post('send-registration-invite', 'sendRegistrationInvite')->name('auth.sendRegistrationInvite');
    });
    Route::post('verify-registration-token', 'verifyRegistrationToken')->name('auth.verifyRegistrationToken');
});

Route::post("test-mail-send" , function() {
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