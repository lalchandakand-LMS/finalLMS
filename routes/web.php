<?php

use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PhoneVerificationController;
use App\Http\Controllers\SmsCampaignController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LiveClassController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CmsPageController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect('/dashboard') : view('landing');
})->name('landing');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
});
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.post');

Route::get('/about', [CmsPageController::class, 'about'])->name('pages.about');
Route::get('/contact', [CmsPageController::class, 'contact'])->name('pages.contact');
Route::get('/faq', [CmsPageController::class, 'faq'])->name('pages.faq');
Route::post('/language', [LocaleController::class, 'update'])->middleware('throttle:20,1')->name('locale.update');

Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'verified', 'activity.log'])->group(function () {
    Route::get('/live-classes', [LiveClassController::class, 'index'])->name('live-classes.index');
    Route::middleware('role:branch_manager')->get('/branch-manager/batches', [BatchController::class, 'branchManagerIndex'])->name('branch-manager.batches.index');
    Route::middleware('role:accountant')->group(function () {
        Route::get('/accountant/invoices', [InvoiceController::class, 'accountantIndex'])->name('accountant.invoices.index');
        Route::post('/accountant/invoices', [InvoiceController::class, 'store'])->name('accountant.invoices.store');
        Route::post('/accountant/invoices/{invoice}/payments', [InvoiceController::class, 'receivePayment'])->name('accountant.invoices.payments.store');
    });
    Route::post('/teacher/batches/{batch}/live-classes', [LiveClassController::class, 'store'])
        ->middleware('role:teacher')
        ->name('teacher.batches.live-classes.store');
    Route::put('/live-classes/{liveClass}', [LiveClassController::class, 'update'])
        ->middleware('role:teacher,admin')
        ->name('live-classes.update');
    Route::delete('/live-classes/{liveClass}', [LiveClassController::class, 'destroy'])
        ->middleware('role:teacher,admin')
        ->name('live-classes.destroy');

    Route::middleware('role:teacher')->get('/teacher/batches', [BatchController::class, 'teacherIndex'])->name('teacher.batches.index');
    Route::middleware('role:student')->get('/my/attendance', [AttendanceController::class, 'studentIndex'])->name('student.attendance.index');
    Route::middleware('role:teacher')->group(function () {
        Route::get('/teacher/batches/{batch}/attendance', [AttendanceController::class, 'index'])->name('teacher.batches.attendance.index');
        Route::post('/teacher/batches/{batch}/attendance', [AttendanceController::class, 'store'])->name('teacher.batches.attendance.store');
        Route::get('/teacher/batches/{batch}/assignments', [AssignmentController::class, 'index'])->name('teacher.batches.assignments.index');
        Route::get('/teacher/batches/{batch}/exams', [ExamController::class, 'teacherIndex'])->name('teacher.batches.exams.index');
        Route::post('/teacher/batches/{batch}/exams', [ExamController::class, 'store'])->name('teacher.batches.exams.store');
        Route::post('/teacher/batches/{batch}/assignments', [AssignmentController::class, 'store'])->name('teacher.batches.assignments.store');
        Route::post('/teacher/assignments/{assignment}/submissions/{submission}/grade', [AssignmentController::class, 'grade'])->name('teacher.assignments.grade');
    });
    Route::middleware('role:student')->group(function () {
        Route::get('/my/invoices', [InvoiceController::class, 'studentIndex'])->name('student.invoices.index');
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('student.assignments.show');
        Route::get('/my/exams', [ExamController::class, 'studentExams'])->name('student.exams.index');
        Route::get('/exams/{exam}', [ExamController::class, 'studentIndex'])->name('student.exams.show');
        Route::post('/exams/{exam}/start', [ExamController::class, 'start'])->name('student.exams.start');
        Route::post('/exam-attempts/{attempt}/submit', [ExamController::class, 'submit'])->name('student.exams.submit');
        Route::post('/assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->name('student.assignments.submit');
    });
    Route::post('/phone/verification/send', [PhoneVerificationController::class, 'send'])
        ->middleware('throttle:3,1')
        ->name('phone.verification.send');
    Route::post('/phone/verification/verify', [PhoneVerificationController::class, 'verify'])
        ->middleware('throttle:6,1')
        ->name('phone.verification.verify');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/certificates/{certificate}', [DashboardController::class, 'certificate'])->name('certificates.show');

    Route::post('/courses/{course}/enroll', [PaymentController::class, 'submit'])->name('courses.enroll');
    Route::post('/courses/{course}/complete', [CourseController::class, 'complete'])->name('courses.complete');
    Route::post('/courses/{course}/lessons/{lesson}/complete', [CourseController::class, 'completeLesson'])
        ->middleware('role:student')
        ->name('courses.lessons.complete');

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'adminIndex'])->name('admin.invoices.index');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('admin.invoices.store');
        Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'receivePayment'])->name('admin.invoices.payments.store');
        Route::get('/batches', [BatchController::class, 'index'])->name('admin.batches.index');
        Route::get('/batches/{batch}/attendance', [AttendanceController::class, 'index'])->name('admin.batches.attendance.index');
        Route::post('/batches/{batch}/attendance', [AttendanceController::class, 'store'])->name('admin.batches.attendance.store');
        Route::post('/branches', [BatchController::class, 'storeBranch'])->name('admin.branches.store');
        Route::put('/branches/{branch}', [BatchController::class, 'updateBranch'])->name('admin.branches.update');
        Route::delete('/branches/{branch}', [BatchController::class, 'destroyBranch'])->name('admin.branches.destroy');
        Route::post('/batches', [BatchController::class, 'store'])->name('admin.batches.store');
        Route::put('/batches/{batch}', [BatchController::class, 'update'])->name('admin.batches.update');
        Route::delete('/batches/{batch}', [BatchController::class, 'destroy'])->name('admin.batches.destroy');
        Route::get('/sms-campaigns', [SmsCampaignController::class, 'index'])->name('admin.sms-campaigns.index');
        Route::post('/sms-campaigns', [SmsCampaignController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('admin.sms-campaigns.store');

        Route::get('/payments', [PaymentController::class, 'index'])->name('admin.payments.index');
        Route::post('/payments/{payment}/approve', [PaymentController::class, 'approve'])->name('admin.payments.approve');
        Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('admin.payments.reject');
        Route::get('/payments/{payment}/proof', [PaymentController::class, 'proof'])->name('admin.payments.proof');

        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');

        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::post('/users/{user}/role', [UserController::class, 'updateRole'])->name('admin.users.updateRole');

        Route::get('/courses', [CourseController::class, 'adminIndex'])->name('admin.courses.index');
        Route::get('/courses/create', [CourseController::class, 'create'])->name('admin.courses.create');
        Route::post('/courses', [CourseController::class, 'store'])->name('admin.courses.store');
        Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('admin.courses.edit');
        Route::put('/courses/{course}', [CourseController::class, 'update'])->name('admin.courses.update');
        Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('admin.courses.destroy');
        Route::post('/courses/{course}/lessons', [CourseController::class, 'storeLesson'])->name('admin.courses.lessons.store');
    });
});
