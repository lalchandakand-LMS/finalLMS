<?php

/*
|--------------------------------------------------------------------------
| Route ownership map
|--------------------------------------------------------------------------
| Keep patterns specific and assign each route to one functional module.
| The platform kernel and module manager routes intentionally remain core-owned.
*/
return [
    'branch_management' => ['admin.branches.*', 'admin.branch-workspace.*', 'branch-manager.*', 'admin.branch-managers.*'],
    'student_management' => ['admin.students.*', 'admin.student-devices.*', 'admin.student-applications.*'],
    'teacher_management' => ['teacher.applications.*', 'admin.teacher-applications.*', 'admin.teachers.*', 'teacher.profile.*'],
    'guardian_management' => ['admin.guardians.*', 'guardian.links.*', 'student.guardian-requests.*'],
    'staff_management' => ['admin.staff.*', 'admin.hr.*'],
    'attendance' => ['teacher.batches.attendance.*', 'student.attendance.*', 'admin.attendance.*'],
    'assignment' => ['teacher.batches.assignments.*', 'teacher.assignments.*', 'student.assignments.*', 'admin.assignments.*'],
    'exam_management' => ['student.exams.*', 'teacher.exams.*', 'teacher.question-set-attempts.*', 'student.question-set-attempts.*', 'student.question-sets.*', 'admin.exams.*', 'admin.question-sets.*'],
    'live_classes' => ['live-classes.*', 'teacher.batches.live-classes.*'],
    'certificates' => ['certificates.*', 'admin.certificates.*'],
    'guardian_portal' => ['guardian.*'],
    'student_portal' => ['student.dashboard.*', 'student.profile.*', 'student.wishlist.*', 'student.courses.*'],
    'lesson_management' => ['courses.lessons.*', 'courses.content.*', 'admin.lessons.*', 'admin.course-content-posts.*'],
    'batch_management' => ['teacher.batches.*', 'admin.batches.*'],
    'course_management' => ['courses.*', 'course-combos.*', 'teacher.courses.*', 'admin.courses.*', 'admin.course-categories.*', 'admin.course-builder.*'],
    'refund_management' => ['admin.finance.refunds.*', 'admin.refunds.*'],
    'expense_management' => ['admin.finance.expenses.*', 'admin.expenses.*'],
    'fee_management' => ['admin.finance.invoices.*', 'admin.invoices.*', 'student.invoices.*', 'admin.finance.invoice-payments.*'],
    'payments' => ['payments.*', 'courses.enroll', 'course-combos.enroll', 'admin.payment-gateways.*'],
    'coupons_promotions' => ['admin.coupons.*', 'admin.flash-sales.*', 'flash-sales.*'],
    'content_store' => ['ebooks.*', 'admin.ebooks.*', 'admin.ebook-orders.*'],
    'sms' => ['admin.sms.*', 'sms.*', 'admin.sms-campaigns.*'],
    'email' => ['admin.email.*', 'email.*'],
    'push_notifications' => ['admin.push-notifications.*', 'push.*'],
    'notice_board' => ['notices.*', 'admin.notices.*', 'admin.dashboard-announcements.*'],
    'mobile_verification' => ['phone.verification.*', 'student-device.*', 'admin.verification-center.*'],
    'website_cms' => ['pages.*', 'news.*', 'admin.website.*', 'admin.pages.*', 'admin.blog.*', 'admin.website-builder.*'],
    'slider' => ['admin.sliders.*', 'sliders.*'],
    'media_library' => ['admin.media.*', 'media.*', 'admin.storage.media.*'],
    'storage' => ['admin.backups.*', 'admin.storage.*'],
    'seo' => ['seo.*', 'admin.seo.*'],
    'analytics' => ['admin.analytics.*', 'analytics.*'],
    'marketing' => ['admin.marketing.*', 'marketing.*'],
    'meta_pixel' => ['meta-pixel.*', 'admin.meta-pixel.*'],
    'meta_ads' => ['admin.meta-ads.*', 'meta-ads.*', 'admin.meta-boost.*'],
    'support_center' => ['admin.support-tickets.*', 'support.*'],
    'reporting' => ['admin.reports.*', 'admin.finance.reports.*', 'admin.analytics.*'],
    'student_mobile_app' => ['api.v1.student.*'],
    'instructor_mobile_app' => ['api.v1.instructor.*', 'api.v1.teacher.*'],
    'mobile_api' => ['api.v1.*'],
    'integrations' => ['admin.addons.*', 'admin.integrations.*'],
];
