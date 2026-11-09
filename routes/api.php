<?php

use App\\Http\\Controllers\\Api\\V1\\AuthController;
use App\\Http\\Controllers\\Api\\V1\\CourseController;
use Illuminate\\Support\\Facades\\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::get('/courses', [CourseController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/courses/{course:slug}', [CourseController::class, 'show'])->middleware(['api.token:optional', 'throttle:60,1']);

    Route::middleware(['api.token', 'throttle:120,1'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/my/courses', [CourseController::class, 'myCourses']);
        Route::get('/instructor/courses', [CourseController::class, 'teachingCourses']);
        Route::get('/instructor/courses/{course:slug}/students', [CourseController::class, 'courseStudents']);
        Route::get('/courses/{course:slug}/lessons/{lesson}', [CourseController::class, 'lesson']);
    });
});
