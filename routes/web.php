<?php

use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminUpdateController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentDeviceAccessController;
use App\Http\Controllers\LessonMediaController;
use App\Http\Controllers\TeacherApplicationController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseWishlistController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\BkashPaymentController;
use App\Http\Controllers\PhoneVerificationController;
use App\Http\Controllers\SmsCampaignController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LiveClassController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceRefundController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CmsPageController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MediaLibraryController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\WebsiteBuilderController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\QuizContestController;
use App\Http\Controllers\FlashSaleController;
use App\Http\Controllers\AdminCouponController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\CommerceBkashController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminCommentController;
use App\Http\Controllers\AdminDashboardAnnouncementController;
use App\Http\Controllers\AdminVisitorPopupController;
use App\Http\Controllers\AdminCourseCategoryController;
use App\Http\Controllers\AdminCourseContentPostController;
use App\Http\Controllers\AdminCourseBuilderController;
use App\Http\Controllers\CourseContentController;
use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'index'])->name('install.index');
Route::post('/install', [InstallController::class, 'install'])->middleware('throttle:5,1')->name('install.store');

Route::get('/', function () {
    return auth()->check() ? redirect('/dashboard') : view('landing');
})->name('landing');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
});
Route::post('/login', [StudentDeviceAccessController::class, 'beginLogin'])->middleware('throttle:5,1')->name('login.post');
Route::get('/student-device/verify', [StudentDeviceAccessController::class, 'showVerify'])->middleware('throttle:10,1')->name('student-device.verify');
Route::post('/student-device/verify', [StudentDeviceAccessController::class, 'verifyPhone'])->middleware('throttle:5,1')->name('student-device.verify-phone');
Route::get('/teacher/apply', [TeacherApplicationController::class, 'create'])->name('teacher.apply');
Route::post('/teacher/apply', [TeacherApplicationController::class, 'store'])->middleware('throttle:3,10')->name('teacher.apply.store');
Route::get('/teacher/apply/verify', [TeacherApplicationController::class, 'showVerify'])->middleware('throttle:10,1')->name('teacher.apply.verify');
Route::post('/teacher/apply/verify', [TeacherApplicationController::class, 'verify'])->middleware('throttle:5,10')->name('teacher.apply.verify.post');

Route::get('/about', [CmsPageController::class, 'about'])->name('pages.about');
Route::get('/contact', [CmsPageController::class, 'contact'])->name('pages.contact');
Route::get('/faq', [CmsPageController::class, 'faq'])->name('pages.faq');
Route::get('/news', [BlogController::class, 'index'])->name('news.index');
Route::get('/news/{post:slug}', [BlogController::class, 'show'])->name('news.show');
Route::get('/store', [StoreController::class, 'index'])->name('store.index');
Route::get('/store/{product:slug}/preview', [StoreController::class, 'preview'])->name('store.preview');
Route::get('/store/{product:slug}', [StoreController::class, 'show'])->name('store.products.show');
Route::get('/store/payments/bkash/callback', [CommerceBkashController::class, 'callback'])->middleware('throttle:20,1')->name('store.payments.bkash.callback');
Route::post('/news/{post:slug}/comments', [CommentController::class, 'storePost'])->middleware(['auth', 'throttle:5,1'])->name('news.comments.store');
Route::post('/courses/{course}/comments', [CommentController::class, 'storeCourse'])->middleware(['auth', 'throttle:5,1'])->name('courses.comments.store');
Route::post('/language', [LocaleController::class, 'update'])->middleware('throttle:20,1')->name('locale.update');
Route::get('/payments/bkash/callback', [BkashPaymentController::class, 'callback'])->middleware('throttle:30,1')->name('payments.bkash.callback');

Route::get('/contests', [QuizContestController::class, 'index'])->name('contests.index');
Route::get('/contests/{contest}', [QuizContestController::class, 'show'])->name('contests.show');

Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
Route::get('/courses/{course}/content/{post:slug}', [CourseContentController::class, 'show'])->name('courses.content.show');
Route::get('/courses/{course}/lessons/{lesson}', [CourseController::class, 'showLesson'])->name('courses.lessons.show');
Route::get('/courses/{course}/lessons/{lesson}/video', [CourseController::class, 'streamLessonVideo'])->name('courses.lessons.video');
Route::get('/courses/{course}/lessons/{lesson}/media/{type}', [LessonMediaController::class, 'show'])->middleware('throttle:120,1')->name('courses.lessons.media');

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
    Route::get('/invoice-payments/{payment}/receipt', [InvoiceController::class, 'receipt'])
        ->middleware('role:admin,accountant')
        ->name('invoice-payments.receipt');
    Route::middleware('role:branch_manager')->group(function () {
        Route::get('/branch-manager/batches', [BatchController::class, 'branchManagerIndex'])->name('branch-manager.batches.index');
        Route::get('/branch-manager/students', [BatchController::class, 'branchManagerStudents'])->name('branch-manager.students.index');
        Route::post('/branch-manager/batches', [BatchController::class, 'branchManagerStore'])->name('branch-manager.batches.store');
        Route::put('/branch-manager/batches/{batch}', [BatchController::class, 'branchManagerUpdate'])->name('branch-manager.batches.update');
    });
    Route::middleware('role:accountant')->group(function () {
        Route::get('/accountant/invoices', [InvoiceController::class, 'accountantIndex'])->name('accountant.invoices.index');
        Route::get('/accountant/refunds', [InvoiceRefundController::class, 'index'])->name('accountant.refunds.index');
        Route::post('/accountant/refunds', [InvoiceRefundController::class, 'store'])->name('accountant.refunds.store');
        Route::get('/accountant/finance-reports', [FinanceReportController::class, 'index'])->name('accountant.finance-reports.index');
        Route::get('/accountant/expenses', [ExpenseController::class, 'index'])->name('accountant.expenses.index');
        Route::post('/accountant/expenses', [ExpenseController::class, 'store'])->name('accountant.expenses.store');
        Route::put('/accountant/expenses/{expense}', [ExpenseController::class, 'update'])->name('accountant.expenses.update');
        Route::delete('/accountant/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('accountant.expenses.destroy');
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
    Route::middleware('role:teacher')->get('/teacher/courses/{course}/lessons', [CourseController::class, 'teacherLessons'])->name('teacher.courses.lessons.index');
    Route::middleware('role:teacher')->post('/teacher/courses/{course}/lessons', [CourseController::class, 'storeLesson'])->name('teacher.courses.lessons.store');
    Route::middleware('role:teacher')->put('/teacher/courses/{course}/lessons/{lesson}', [CourseController::class, 'updateLesson'])->name('teacher.courses.lessons.update');
    Route::middleware('role:teacher')->delete('/teacher/courses/{course}/lessons/{lesson}', [CourseController::class, 'destroyLesson'])->name('teacher.courses.lessons.destroy');
    Route::middleware('role:student')->get('/my/attendance', [AttendanceController::class, 'studentIndex'])->name('student.attendance.index');
    Route::middleware('role:teacher')->group(function () {
        Route::get('/teacher/batches/{batch}/attendance', [AttendanceController::class, 'index'])->name('teacher.batches.attendance.index');
        Route::post('/teacher/batches/{batch}/attendance', [AttendanceController::class, 'store'])->name('teacher.batches.attendance.store');
        Route::get('/teacher/batches/{batch}/assignments', [AssignmentController::class, 'index'])->name('teacher.batches.assignments.index');
        Route::get('/teacher/batches/{batch}/exams', [ExamController::class, 'teacherIndex'])->name('teacher.batches.exams.index');
        Route::get('/teacher/batches/{batch}/question-bank', [ExamController::class, 'questionBank'])->name('teacher.batches.question-bank.index');
        Route::post('/teacher/batches/{batch}/question-bank', [ExamController::class, 'storeBankQuestion'])->name('teacher.batches.question-bank.store');
        Route::put('/teacher/batches/{batch}/question-bank/{question}', [ExamController::class, 'updateBankQuestion'])->name('teacher.batches.question-bank.update');
        Route::delete('/teacher/batches/{batch}/question-bank/{question}', [ExamController::class, 'destroyBankQuestion'])->name('teacher.batches.question-bank.destroy');
        Route::get('/teacher/exams/{exam}/results', [ExamController::class, 'teacherResults'])->name('teacher.exams.results');
        Route::post('/teacher/batches/{batch}/exams', [ExamController::class, 'store'])->name('teacher.batches.exams.store');
        Route::post('/teacher/batches/{batch}/assignments', [AssignmentController::class, 'store'])->middleware('throttle:10,1')->name('teacher.batches.assignments.store');
        Route::post('/teacher/assignments/{assignment}/submissions/{submission}/grade', [AssignmentController::class, 'grade'])->middleware('throttle:30,1')->name('teacher.assignments.grade');
    });
    Route::get('/assignment-submissions/{submission}/attachment', [AssignmentController::class, 'download'])->name('assignment-submissions.attachment');
    Route::middleware('role:student')->group(function () {
        Route::get('/store/cart', [StoreController::class, 'cart'])->name('store.cart');
        Route::post('/store/cart/{product:slug}', [StoreController::class, 'add'])->middleware('throttle:30,1')->name('store.cart.add');
        Route::put('/store/cart/items/{item}', [StoreController::class, 'update'])->name('store.cart.update');
        Route::delete('/store/cart/items/{item}', [StoreController::class, 'remove'])->name('store.cart.remove');
        Route::post('/store/checkout', [StoreController::class, 'checkout'])->middleware('throttle:10,1')->name('store.checkout');
        Route::get('/store/orders', [StoreController::class, 'orders'])->name('store.orders');
        Route::post('/store/orders/{order}/pay/bkash', [CommerceBkashController::class, 'initiate'])->middleware('throttle:5,1')->name('store.orders.pay.bkash');
        Route::get('/store/downloads/{permission}', [StoreController::class, 'download'])->middleware('throttle:10,1')->name('store.download');
        Route::get('/store/reader/{permission}', [StoreController::class, 'reader'])->middleware('throttle:20,1')->name('store.reader');
        Route::get('/my/wishlist', [CourseWishlistController::class, 'index'])->name('student.wishlist.index');
        Route::post('/courses/{course}/wishlist', [CourseWishlistController::class, 'store'])->middleware('throttle:30,1')->name('student.wishlist.store');
        Route::delete('/courses/{course}/wishlist', [CourseWishlistController::class, 'destroy'])->middleware('throttle:30,1')->name('student.wishlist.destroy');
        Route::post('/courses/{course}/payments/bkash', [BkashPaymentController::class, 'initiate'])->middleware('throttle:10,1')->name('courses.payments.bkash');
        Route::get('/my/courses', [CourseController::class, 'myCourses'])->name('student.courses.index');
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

    Route::post('/contests/{contest}/submit', [QuizContestController::class, 'submit'])->middleware('throttle:5,1')->name('contests.submit');

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
        Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('admin.analytics.index');
        Route::get('/dashboard-announcements', [AdminDashboardAnnouncementController::class, 'index'])->name('admin.dashboard-announcements.index');
        Route::get('/dashboard-announcements/create', [AdminDashboardAnnouncementController::class, 'create'])->name('admin.dashboard-announcements.create');
        Route::post('/dashboard-announcements', [AdminDashboardAnnouncementController::class, 'store'])->name('admin.dashboard-announcements.store');
        Route::get('/dashboard-announcements/{dashboardAnnouncement}/edit', [AdminDashboardAnnouncementController::class, 'edit'])->name('admin.dashboard-announcements.edit');
        Route::put('/dashboard-announcements/{dashboardAnnouncement}', [AdminDashboardAnnouncementController::class, 'update'])->name('admin.dashboard-announcements.update');
        Route::patch('/dashboard-announcements/{dashboardAnnouncement}/toggle', [AdminDashboardAnnouncementController::class, 'toggle'])->name('admin.dashboard-announcements.toggle');
        Route::delete('/dashboard-announcements/{dashboardAnnouncement}', [AdminDashboardAnnouncementController::class, 'destroy'])->name('admin.dashboard-announcements.destroy');
        Route::get('/visitor-popups', [AdminVisitorPopupController::class, 'index'])->name('admin.visitor-popups.index');
        Route::get('/visitor-popups/create', [AdminVisitorPopupController::class, 'create'])->name('admin.visitor-popups.create');
        Route::post('/visitor-popups', [AdminVisitorPopupController::class, 'store'])->name('admin.visitor-popups.store');
        Route::get('/visitor-popups/{visitorPopup}/edit', [AdminVisitorPopupController::class, 'edit'])->name('admin.visitor-popups.edit');
        Route::put('/visitor-popups/{visitorPopup}', [AdminVisitorPopupController::class, 'update'])->name('admin.visitor-popups.update');
        Route::patch('/visitor-popups/{visitorPopup}/toggle', [AdminVisitorPopupController::class, 'toggle'])->name('admin.visitor-popups.toggle');
        Route::delete('/visitor-popups/{visitorPopup}', [AdminVisitorPopupController::class, 'destroy'])->name('admin.visitor-popups.destroy');
        Route::get('/comments', [AdminCommentController::class, 'index'])->name('admin.comments.index');
        Route::put('/comments/{comment}', [AdminCommentController::class, 'update'])->name('admin.comments.update');
        Route::delete('/comments/{comment}', [AdminCommentController::class, 'destroy'])->name('admin.comments.destroy');
        Route::get('/student-devices', [StudentDeviceAccessController::class, 'adminIndex'])->name('admin.student-devices.index');
        Route::get('/teacher-applications', [TeacherApplicationController::class, 'adminIndex'])->name('admin.teacher-applications.index');
        Route::get('/teacher-applications/{application}/documents/{type}', [TeacherApplicationController::class, 'document'])->name('admin.teacher-applications.document');
        Route::post('/teacher-applications/{application}/approve', [TeacherApplicationController::class, 'approve'])->name('admin.teacher-applications.approve');
        Route::post('/teacher-applications/{application}/reject', [TeacherApplicationController::class, 'reject'])->name('admin.teacher-applications.reject');
        Route::post('/student-devices/{device}/approve', [StudentDeviceAccessController::class, 'approve'])->name('admin.student-devices.approve');
        Route::post('/student-devices/{device}/reject', [StudentDeviceAccessController::class, 'reject'])->name('admin.student-devices.reject');
        Route::post('/student-devices/{device}/revoke', [StudentDeviceAccessController::class, 'revoke'])->name('admin.student-devices.revoke');
        Route::get('/updates', [AdminUpdateController::class, 'index'])->name('admin.updates.index');
        Route::post('/updates/check', [AdminUpdateController::class, 'check'])->middleware('throttle:3,10')->name('admin.updates.check');
        Route::post('/updates/prepare', [AdminUpdateController::class, 'prepare'])->middleware('throttle:4,10')->name('admin.updates.prepare');
        Route::post('/updates/{preparation}/extract', [AdminUpdateController::class, 'extract'])->middleware('throttle:5,10')->name('admin.updates.extract');
        Route::post('/updates/{preparation}/backup', [AdminUpdateController::class, 'createBackup'])->middleware('throttle:6,10')->name('admin.updates.backup');
        Route::post('/updates/{preparation}/preflight', [AdminUpdateController::class, 'preflight'])->middleware('throttle:7,10')->name('admin.updates.preflight');
        Route::get('/flash-sales', [FlashSaleController::class, 'index'])->name('admin.flash-sales.index');
        Route::post('/flash-sales', [FlashSaleController::class, 'store'])->name('admin.flash-sales.store');
        Route::put('/flash-sales/{flashSale}', [FlashSaleController::class, 'update'])->name('admin.flash-sales.update');
        Route::delete('/flash-sales/{flashSale}', [FlashSaleController::class, 'destroy'])->name('admin.flash-sales.destroy');
        Route::get('/contests', [QuizContestController::class, 'adminIndex'])->name('admin.contests.index');
        Route::post('/contests', [QuizContestController::class, 'store'])->middleware('throttle:10,1')->name('admin.contests.store');
        Route::post('/contests/{contest}/draw', [QuizContestController::class, 'draw'])->middleware('throttle:3,10')->name('admin.contests.draw');
        Route::get('/invoices', [InvoiceController::class, 'adminIndex'])->name('admin.invoices.index');
        Route::get('/refunds', [InvoiceRefundController::class, 'index'])->name('admin.refunds.index');
        Route::post('/refunds', [InvoiceRefundController::class, 'store'])->name('admin.refunds.store');
        Route::post('/refunds/{refund}/approve', [InvoiceRefundController::class, 'approve'])->name('admin.refunds.approve');
        Route::post('/refunds/{refund}/reject', [InvoiceRefundController::class, 'reject'])->name('admin.refunds.reject');
        Route::get('/finance-reports', [FinanceReportController::class, 'index'])->name('admin.finance-reports.index');
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('admin.expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('admin.expenses.store');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('admin.expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('admin.expenses.destroy');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('admin.invoices.store');
        Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'receivePayment'])->name('admin.invoices.payments.store');
        Route::get('/batches', [BatchController::class, 'index'])->name('admin.batches.index');
        Route::get('/batches/{batch}/assignments', [AssignmentController::class, 'index'])->name('admin.batches.assignments.index');
        Route::post('/batches/{batch}/assignments', [AssignmentController::class, 'store'])->middleware('throttle:10,1')->name('admin.batches.assignments.store');
        Route::post('/assignments/{assignment}/submissions/{submission}/grade', [AssignmentController::class, 'grade'])->middleware('throttle:30,1')->name('admin.assignments.grade');
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

        Route::get('/backups', [BackupController::class, 'index'])->name('admin.backups.index');
        Route::post('/backups', [BackupController::class, 'create'])->name('admin.backups.create');
        Route::get('/backups/{filename}', [BackupController::class, 'download'])->name('admin.backups.download');
        Route::post('/backups/restore', [BackupController::class, 'restore'])->middleware('throttle:2,10')->name('admin.backups.restore');
        Route::get('/website-builder', [WebsiteBuilderController::class, 'index'])->name('admin.website-builder.index');
        Route::post('/website-builder', [WebsiteBuilderController::class, 'update'])->name('admin.website-builder.update');
        Route::post('/website-builder/draft', [WebsiteBuilderController::class, 'saveDraft'])->name('admin.website-builder.draft');
        Route::get('/website-builder/preview', [WebsiteBuilderController::class, 'previewDraft'])->name('admin.website-builder.preview');
        Route::post('/website-builder/publish-draft', [WebsiteBuilderController::class, 'publishDraft'])->name('admin.website-builder.publish-draft');
        Route::post('/website-builder/rollback', [WebsiteBuilderController::class, 'rollback'])->name('admin.website-builder.rollback');
        Route::post('/website-builder/custom-blocks', [WebsiteBuilderController::class, 'saveCustomBlock'])->name('admin.website-builder.custom-blocks.store');
        Route::delete('/website-builder/custom-blocks', [WebsiteBuilderController::class, 'deleteCustomBlock'])->name('admin.website-builder.custom-blocks.destroy');
        Route::get('/media', [MediaLibraryController::class, 'index'])->name('admin.media.index');
        Route::post('/media', [MediaLibraryController::class, 'store'])->name('admin.media.store');
        Route::delete('/media/{media}', [MediaLibraryController::class, 'destroy'])->name('admin.media.destroy');
        Route::get('/news', [BlogController::class, 'adminIndex'])->name('admin.news.index');
        Route::get('/news/create', [BlogController::class, 'create'])->name('admin.news.create');
        Route::post('/news', [BlogController::class, 'store'])->name('admin.news.store');
        Route::get('/news/{post}/edit', [BlogController::class, 'edit'])->name('admin.news.edit');
        Route::put('/news/{post}', [BlogController::class, 'update'])->name('admin.news.update');
        Route::delete('/news/{post}', [BlogController::class, 'destroy'])->name('admin.news.destroy');
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');

        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::post('/users/{user}/role', [UserController::class, 'updateRole'])->name('admin.users.updateRole');

        Route::get('/products', [AdminProductController::class, 'index'])->name('admin.products.index');
        Route::post('/products', [AdminProductController::class, 'store'])->name('admin.products.store');
        Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('admin.products.update');
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('admin.products.destroy');
        Route::post('/product-categories', [AdminProductController::class, 'storeCategory'])->name('admin.product-categories.store');
        Route::put('/product-categories/{category}', [AdminProductController::class, 'updateCategory'])->name('admin.product-categories.update');
        Route::delete('/product-categories/{category}', [AdminProductController::class, 'destroyCategory'])->name('admin.product-categories.destroy');
        Route::get('/coupons', [AdminCouponController::class, 'index'])->name('admin.coupons.index');
        Route::post('/coupons', [AdminCouponController::class, 'store'])->name('admin.coupons.store');
        Route::put('/coupons/{coupon}', [AdminCouponController::class, 'update'])->name('admin.coupons.update');
        Route::delete('/coupons/{coupon}', [AdminCouponController::class, 'destroy'])->name('admin.coupons.destroy');
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
        Route::get('/orders/{order}/proof', [AdminOrderController::class, 'proof'])->name('admin.orders.proof');
        Route::post('/orders/{order}/approve', [AdminOrderController::class, 'approve'])->name('admin.orders.approve');
        Route::post('/orders/{order}/reject', [AdminOrderController::class, 'reject'])->name('admin.orders.reject');
        Route::post('/orders/{order}/record-refund', [AdminOrderController::class, 'recordRefund'])->name('admin.orders.record-refund');
        Route::post('/download-permissions/{permission}/revoke', [AdminOrderController::class, 'revokeDownload'])->name('admin.download-permissions.revoke');
        Route::get('/course-categories', [AdminCourseCategoryController::class, 'index'])->name('admin.course-categories.index');
        Route::post('/course-categories', [AdminCourseCategoryController::class, 'store'])->name('admin.course-categories.store');
        Route::get('/course-categories/{courseCategory}/edit', [AdminCourseCategoryController::class, 'edit'])->name('admin.course-categories.edit');
        Route::put('/course-categories/{courseCategory}', [AdminCourseCategoryController::class, 'update'])->name('admin.course-categories.update');
        Route::delete('/course-categories/{courseCategory}', [AdminCourseCategoryController::class, 'destroy'])->name('admin.course-categories.destroy');
        Route::get('/course-content-posts', [AdminCourseContentPostController::class, 'index'])->name('admin.course-content-posts.index');
        Route::get('/course-content-posts/create', [AdminCourseContentPostController::class, 'create'])->name('admin.course-content-posts.create');
        Route::post('/course-content-posts', [AdminCourseContentPostController::class, 'store'])->name('admin.course-content-posts.store');
        Route::get('/course-content-posts/{courseContentPost}/edit', [AdminCourseContentPostController::class, 'edit'])->name('admin.course-content-posts.edit');
        Route::put('/course-content-posts/{courseContentPost}', [AdminCourseContentPostController::class, 'update'])->name('admin.course-content-posts.update');
        Route::delete('/course-content-posts/{courseContentPost}', [AdminCourseContentPostController::class, 'destroy'])->name('admin.course-content-posts.destroy');
        Route::get('/courses', [CourseController::class, 'adminIndex'])->name('admin.courses.index');
        Route::get('/courses/{course}/builder', [AdminCourseBuilderController::class, 'edit'])->name('admin.course-builder.edit');
        Route::put('/courses/{course}/builder', [AdminCourseBuilderController::class, 'update'])->name('admin.course-builder.update');
        Route::get('/courses/create', [CourseController::class, 'create'])->name('admin.courses.create');
        Route::post('/courses', [CourseController::class, 'store'])->name('admin.courses.store');
        Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('admin.courses.edit');
        Route::put('/courses/{course}', [CourseController::class, 'update'])->name('admin.courses.update');
        Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('admin.courses.destroy');
        Route::post('/courses/{course}/lessons', [CourseController::class, 'storeLesson'])->name('admin.courses.lessons.store');
        Route::get('/courses/{course}/lessons', [CourseController::class, 'teacherLessons'])->name('admin.courses.lessons.index');
        Route::put('/courses/{course}/lessons/{lesson}', [CourseController::class, 'updateLesson'])->name('admin.courses.lessons.update');
        Route::delete('/courses/{course}/lessons/{lesson}', [CourseController::class, 'destroyLesson'])->name('admin.courses.lessons.destroy');
    });
});
