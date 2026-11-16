<?php

/*
|--------------------------------------------------------------------------
| API route ownership
|--------------------------------------------------------------------------
| API routes are currently largely unnamed, so ownership is declared by
| Laravel route URI. API endpoints require Mobile API plus their feature
| module where applicable.
*/
return [
    'mobile_api' => ['api/v1/*'],
    'identity' => ['api/v1/auth/*'],
    'mobile_verification' => ['api/v1/auth/verify-device', 'api/v1/auth/device-status'],
    'student_management' => ['api/v1/my/*'],
    'teacher_management' => ['api/v1/instructor/*'],
    'attendance' => ['api/v1/instructor/attendance/*', 'api/v1/instructor/batches/*/attendance/*', 'api/v1/attendance/qr/*'],
    'batch_management' => ['api/v1/instructor/batches*'],
    'student_mobile_app' => [
        'api/v1/attendance/qr/*', 'api/v1/my/*', 'api/v1/checkout/*', 'api/v1/assignments/*',
        'api/v1/exams/*', 'api/v1/exam-attempts/*',
        'api/v1/question-set-attempts/*',
    ],
    'instructor_mobile_app' => ['api/v1/instructor/*'],
    'course_management' => [
        'api/v1/courses', 'api/v1/courses/*', 'api/v1/checkout/courses*', 'api/v1/instructor/courses*',
        'api/v1/my/courses*', 'api/v1/my/wishlist*', 'api/v1/courses/*/wishlist',
        'api/v1/my/cart*', 'api/v1/my/courses/*/reviews', 'api/v1/courses/*/reviews',
    ],
    'lesson_management' => [
        'api/v1/courses/*/lessons', 'api/v1/courses/*/lessons/*',
        'api/v1/instructor/courses/*/lessons', 'api/v1/instructor/courses/*/lessons/*',
    ],
    'assignment' => ['api/v1/assignments/*', 'api/v1/instructor/assignments/*'],
    'exam_management' => [
        'api/v1/exams/*', 'api/v1/exam-attempts/*',
        'api/v1/question-set-attempts/*', 'api/v1/instructor/exams/*',
        'api/v1/my/quizzes*', 'api/v1/courses/*/question-sets/*',
    ],
    'live_classes' => ['api/v1/instructor/live-classes*', 'api/v1/instructor/batches/*/live-classes'],
    'payments' => ['api/v1/checkout/*', 'api/v1/my/payments*', 'api/v1/my/invoices*', 'api/v1/my/cart/checkout/*'],
    'fee_management' => ['api/v1/my/invoices*'],
    'coupons_promotions' => ['api/v1/my/cart/coupon'],
    'notice_board' => ['api/v1/my/notices*', 'api/v1/instructor/notices*'],
    'support_center' => ['api/v1/my/support-tickets*'],
];
