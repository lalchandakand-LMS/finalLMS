<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CourseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/verify-device', [AuthController::class, 'verifyDevice'])->middleware('throttle:5,1');
    Route::post('/auth/device-status', [AuthController::class, 'deviceStatus'])->middleware('throttle:30,1');

    Route::get('/courses', [CourseController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/courses/{course:slug}', [CourseController::class, 'show'])->middleware(['api.token:optional', 'throttle:60,1']);

    Route::middleware(['api.token', 'throttle:120,1'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/my/courses', [CourseController::class, 'myCourses']);\n        Route::get('/my/assignments', [StudentAssignmentController::class, 'index']);\n        Route::get('/assignments/{assignment}', [StudentAssignmentController::class, 'show']);\n        Route::post('/assignments/{assignment}/submit', [StudentAssignmentController::class, 'submit'])->middleware('throttle:10,1');
        Route::get('/my/wishlist', [CourseController::class, 'wishlist']);
        Route::post('/courses/{course:slug}/wishlist', [CourseController::class, 'addToWishlist']);
        Route::delete('/courses/{course:slug}/wishlist', [CourseController::class, 'removeFromWishlist']);
        Route::get('/instructor/courses', [CourseController::class, 'teachingCourses']);
        Route::get('/instructor/courses/{course:slug}/students', [CourseController::class, 'courseStudents']);
        Route::get('/courses/{course:slug}/lessons/{lesson}', [CourseController::class, 'lesson']);
        Route::get('/courses/{course:slug}/lessons/{lesson}/video', [CourseController::class, 'streamLessonVideo']);
        Route::post('/courses/{course:slug}/lessons/{lesson}/complete', [CourseController::class, 'completeLesson']);
    });
});
