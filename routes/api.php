<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\InstructorAssignmentController;
use App\Http\Controllers\Api\V1\StudentAssignmentController;
use App\Http\Controllers\Api\V1\StudentExamController;
use App\Http\Controllers\Api\V1\StudentCourseQuizController;
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
        Route::get('/my/courses', [CourseController::class, 'myCourses']);
        Route::get('/my/assignments', [StudentAssignmentController::class, 'index']);
        Route::get('/assignments/{assignment}', [StudentAssignmentController::class, 'show']);
        Route::post('/assignments/{assignment}/submit', [StudentAssignmentController::class, 'submit'])->middleware('throttle:10,1');
        Route::get('/my/wishlist', [CourseController::class, 'wishlist']);
        Route::post('/courses/{course:slug}/wishlist', [CourseController::class, 'addToWishlist']);
        Route::delete('/courses/{course:slug}/wishlist', [CourseController::class, 'removeFromWishlist']);
        Route::get('/instructor/batches', [InstructorAssignmentController::class, 'batches']);
        Route::get('/instructor/assignments', [InstructorAssignmentController::class, 'index']);
        Route::post('/instructor/batches/{batch}/assignments', [InstructorAssignmentController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/instructor/assignments/{assignment}/submissions', [InstructorAssignmentController::class, 'submissions']);
        Route::patch('/instructor/assignments/{assignment}/submissions/{submission}', [InstructorAssignmentController::class, 'grade'])->middleware('throttle:20,1');
        Route::get('/instructor/courses', [CourseController::class, 'teachingCourses']);
        Route::get('/instructor/courses/{course:slug}/students', [CourseController::class, 'courseStudents']);
        Route::get('/courses/{course:slug}/lessons/{lesson}', [CourseController::class, 'lesson']);
        Route::get('/courses/{course:slug}/lessons/{lesson}/video', [CourseController::class, 'streamLessonVideo']);
        Route::post('/courses/{course:slug}/lessons/{lesson}/complete', [CourseController::class, 'completeLesson']);
        Route::get('/my/exams', [StudentExamController::class, 'index']);
        Route::get('/exams/{exam}', [StudentExamController::class, 'show']);
        Route::post('/exams/{exam}/start', [StudentExamController::class, 'start'])->middleware('throttle:10,1');
        Route::put('/exam-attempts/{attempt}/answers', [StudentExamController::class, 'saveAnswers'])->middleware('throttle:120,1');
        Route::post('/exam-attempts/{attempt}/submit', [StudentExamController::class, 'submit'])->middleware('throttle:10,1');
        Route::get('/my/quizzes', [StudentCourseQuizController::class, 'index']);
        Route::get('/courses/{course:slug}/question-sets/{questionSet}', [StudentCourseQuizController::class, 'show']);
        Route::post('/courses/{course:slug}/question-sets/{questionSet}/start', [StudentCourseQuizController::class, 'start'])->middleware('throttle:10,1');
        Route::get('/question-set-attempts/{attempt}', [StudentCourseQuizController::class, 'attempt']);
        Route::put('/question-set-attempts/{attempt}/answers', [StudentCourseQuizController::class, 'saveAnswers'])->middleware('throttle:120,1');
        Route::post('/question-set-attempts/{attempt}/submit', [StudentCourseQuizController::class, 'submit'])->middleware('throttle:10,1');
    });
});
