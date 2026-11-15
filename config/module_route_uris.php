<?php

/*
|--------------------------------------------------------------------------
| API route ownership
|--------------------------------------------------------------------------
| API routes are currently largely unnamed, so ownership is declared by
| Laravel route URI. Every API route requires Mobile API plus its feature
| module where applicable.
*/
return [
    'mobile_api' => ['api/v1/*'],
    'student_mobile_app' => [
        'api/v1/my/*', 'api/v1/checkout/*', 'api/v1/assignments/*',
        'api/v1/exams/*', 'api/v1/exam-attempts/*',
        'api/v1/question-set-attempts/*',
    ],
    'instructor_mobile_app' => ['api/v1/instructor/*'],
    'course_management' => ['api/v1/courses', 'api/v1/courses/*'],
    'lesson_management' => [
        'api/v1/courses/*/lessons', 'api/v1/courses/*/lessons/*',
        'api/v1/instructor/courses/*/lessons', 'api/v1/instructor/courses/*/lessons/*',
    ],
    'assignment' => ['api/v1/assignments/*', 'api/v1/instructor/assignments/*'],
    'exam_management' => [
        'api/v1/exams/*', 'api/v1/exam-attempts/*',
        'api/v1/question-set-attempts/*', 'api/v1/instructor/exams/*',
    ],
    'live_classes' => ['api/v1/instructor/live-classes*', 'api/v1/instructor/batches/*/live-classes'],
    'payments' => ['api/v1/checkout/*', 'api/v1/my/payments*', 'api/v1/my/invoices*'],
    'notice_board' => ['api/v1/my/notices*', 'api/v1/instructor/notices*'],
    'support_center' => ['api/v1/my/support-tickets*'],
];
