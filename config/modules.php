<?php

return [
    // Protected platform foundations.
    'core' => ['name' => 'Core Platform', 'description' => 'Application kernel, shared infrastructure, tenancy and service contracts.', 'type' => 'core', 'dependencies' => [], 'protected' => true],
    'identity' => ['name' => 'Identity & Access', 'description' => 'Authentication, roles, permissions, MFA, trusted devices and sessions.', 'type' => 'core', 'dependencies' => ['core'], 'protected' => true],

    // Organization and people.
    'branch_management' => ['name' => 'Branch Management', 'description' => 'Branches, branch managers, locations and branch-level settings.', 'type' => 'module', 'dependencies' => ['core', 'identity']],
    'student_management' => ['name' => 'Student Management', 'description' => 'Student profiles, enrollment, lifecycle and records.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'branch_management']],
    'teacher_management' => ['name' => 'Teacher Management', 'description' => 'Teacher profiles, onboarding, KYC and workload.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'branch_management']],
    'guardian_management' => ['name' => 'Guardian Management', 'description' => 'Guardian accounts, student linking and family access.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'student_management']],
    'staff_management' => ['name' => 'Staff & HR', 'description' => 'Staff records, roles, attendance and internal workflows.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'branch_management']],

    // Academic delivery.
    'course_management' => ['name' => 'Course Management', 'description' => 'Courses, categories, curriculum and enrollment.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'teacher_management']],
    'lesson_management' => ['name' => 'Lesson & Learning Content', 'description' => 'Lessons, learning materials, video and course content.', 'type' => 'module', 'dependencies' => ['core', 'course_management', 'storage']],
    'batch_management' => ['name' => 'Batch & Class Management', 'description' => 'Batches, class groups and schedules.', 'type' => 'module', 'dependencies' => ['core', 'course_management', 'branch_management']],
    'attendance' => ['name' => 'Attendance', 'description' => 'Student and staff attendance tracking.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'branch_management']],
    'assignment' => ['name' => 'Assignments', 'description' => 'Assignment publishing, submissions and grading.', 'type' => 'module', 'dependencies' => ['core', 'course_management', 'student_management']],
    'exam_management' => ['name' => 'Exams & Assessments', 'description' => 'Exams, quizzes, question sets, grading and results.', 'type' => 'module', 'dependencies' => ['core', 'course_management', 'student_management']],
    'live_classes' => ['name' => 'Live Classes', 'description' => 'Live class scheduling, access and attendance.', 'type' => 'module', 'dependencies' => ['core', 'course_management', 'teacher_management']],
    'certificates' => ['name' => 'Certificates', 'description' => 'Certificate templates, issuance and verification.', 'type' => 'module', 'dependencies' => ['core', 'course_management', 'student_management']],
    'student_portal' => ['name' => 'Student Portal', 'description' => 'Student dashboard, learning progress and self-service.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'student_management', 'course_management']],
    'guardian_portal' => ['name' => 'Guardian Portal', 'description' => 'Guardian dashboard, linked student progress and notices.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'guardian_management']],

    // Finance and commerce.
    'finance' => ['name' => 'Finance Core', 'description' => 'Financial ledger and shared billing infrastructure.', 'type' => 'module', 'dependencies' => ['core', 'identity']],
    'fee_management' => ['name' => 'Fees & Invoices', 'description' => 'Student fees, invoices, installments and collections.', 'type' => 'module', 'dependencies' => ['core', 'finance', 'student_management']],
    'expense_management' => ['name' => 'Expenses', 'description' => 'Expense entries, approvals and accounting reports.', 'type' => 'module', 'dependencies' => ['core', 'finance']],
    'refund_management' => ['name' => 'Refunds', 'description' => 'Refund requests, approvals and reconciliation.', 'type' => 'module', 'dependencies' => ['core', 'finance', 'payments']],
    'payments' => ['name' => 'Payment Services', 'description' => 'Payment orchestration and provider adapters.', 'type' => 'module', 'dependencies' => ['core', 'finance']],
    'coupons_promotions' => ['name' => 'Coupons & Promotions', 'description' => 'Discount codes, promotions and flash sales.', 'type' => 'module', 'dependencies' => ['core', 'payments']],
    'content_store' => ['name' => 'eBook & Digital Store', 'description' => 'Digital products, eBooks, orders and storefront.', 'type' => 'module', 'dependencies' => ['core', 'payments', 'storage']],

    // Communication and verification.
    'communications' => ['name' => 'Communications Core', 'description' => 'Shared notification dispatch and delivery tracking.', 'type' => 'module', 'dependencies' => ['core', 'identity']],
    'sms' => ['name' => 'SMS Gateway', 'description' => 'SMS providers, templates, campaigns and delivery reports.', 'type' => 'module', 'dependencies' => ['core', 'communications']],
    'email' => ['name' => 'Email & Gmail', 'description' => 'Transactional email, SMTP and email verification.', 'type' => 'module', 'dependencies' => ['core', 'communications']],
    'push_notifications' => ['name' => 'Push Notifications', 'description' => 'Web and mobile push notification delivery.', 'type' => 'module', 'dependencies' => ['core', 'communications']],
    'notice_board' => ['name' => 'Notice Board', 'description' => 'Announcements, audience targeting and read receipts.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'communications']],
    'mobile_verification' => ['name' => 'Mobile Verification', 'description' => 'Phone OTP, verification and recovery flows.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'sms']],

    // Website, media and growth.
    'website_cms' => ['name' => 'Website CMS', 'description' => 'Website builder, pages, blog and public content.', 'type' => 'module', 'dependencies' => ['core']],
    'slider' => ['name' => 'Website Slider & Banners', 'description' => 'Homepage sliders, promotional banners, scheduling and media placement.', 'type' => 'module', 'dependencies' => ['core', 'website_cms', 'storage']],
    'media_library' => ['name' => 'Media Library', 'description' => 'Image, document and media asset management.', 'type' => 'module', 'dependencies' => ['core', 'storage']],
    'storage' => ['name' => 'Storage & Backups', 'description' => 'Local/cloud storage, backups and media infrastructure.', 'type' => 'module', 'dependencies' => ['core']],
    'seo' => ['name' => 'SEO', 'description' => 'Metadata, sitemap, structured data and search optimization.', 'type' => 'module', 'dependencies' => ['core', 'website_cms']],
    'analytics' => ['name' => 'Analytics & Search Console', 'description' => 'Website analytics, Google Search Console and reporting.', 'type' => 'module', 'dependencies' => ['core', 'website_cms']],
    'marketing' => ['name' => 'Marketing Center', 'description' => 'Campaign planning, audience and growth tools.', 'type' => 'module', 'dependencies' => ['core']],
    'popup_ads' => ['name' => 'Popup Ads & Campaigns', 'description' => 'Admin-managed popup ads, rich text, images, custom links, scheduling, targeting, display frequency and campaign analytics.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'storage']],
    'meta_pixel' => ['name' => 'Meta Pixel', 'description' => 'Facebook/Instagram Pixel events and consent controls.', 'type' => 'module', 'dependencies' => ['core', 'marketing', 'website_cms']],
    'meta_ads' => ['name' => 'Meta Ads & Boost', 'description' => 'Meta campaign planning and authorized ad management.', 'type' => 'module', 'dependencies' => ['core', 'marketing', 'meta_pixel']],
    'support_center' => ['name' => 'Support Center', 'description' => 'Support tickets, replies and customer assistance.', 'type' => 'module', 'dependencies' => ['core', 'identity']],

    // Reporting and client applications.
    'reporting' => ['name' => 'Reports & Analytics', 'description' => 'Operational, academic and financial reporting.', 'type' => 'module', 'dependencies' => ['core']],
    'mobile_api' => ['name' => 'Mobile App API', 'description' => 'Versioned API and device security for mobile apps.', 'type' => 'module', 'dependencies' => ['core', 'identity']],
    'student_mobile_app' => ['name' => 'Student Mobile App', 'description' => 'Student application API capabilities and app services.', 'type' => 'module', 'dependencies' => ['core', 'mobile_api', 'student_portal']],
    'instructor_mobile_app' => ['name' => 'Instructor Mobile App', 'description' => 'Instructor application API capabilities and app services.', 'type' => 'module', 'dependencies' => ['core', 'mobile_api', 'teacher_management']],

    // Independent first-party builders and AI modules.
    'course_builder' => ['name' => 'Course Create & Curriculum Builder', 'description' => 'Course authoring, curriculum, lessons, resources and publishing tools.', 'type' => 'module', 'dependencies' => ['core', 'course_management', 'lesson_management']],
    'course_content_studio' => ['name' => 'Course Content Studio', 'description' => 'Reusable course posts, learning blocks and content authoring.', 'type' => 'module', 'dependencies' => ['core', 'course_management', 'lesson_management']],
    'blog_post_builder' => ['name' => 'Post & Blog Builder', 'description' => 'News, blog posts, categories, tags, comments and SEO authoring.', 'type' => 'module', 'dependencies' => ['core', 'website_cms']],
    'page_builder' => ['name' => 'Page & Landing Builder', 'description' => 'Reusable page sections, landing pages, preview and publishing.', 'type' => 'module', 'dependencies' => ['core', 'website_cms']],
    'ai_builder' => ['name' => 'AI Builder', 'description' => 'Draft website, course, quiz, notice and content assets for human review.', 'type' => 'module', 'dependencies' => ['core', 'identity']],
    'ai_developer' => ['name' => 'AI Developer', 'description' => 'Permission-scoped diagnostics and development assistance.', 'type' => 'module', 'dependencies' => ['core', 'identity']],

    // Independent verification workflows and delivery adapters.
    'otp_verification' => ['name' => 'OTP Verification', 'description' => 'Provider-neutral OTP workflow, expiry, replay protection and throttling.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'communications']],
    'sms_otp' => ['name' => 'SMS OTP Adapter', 'description' => 'Deliver OTP through an enabled SMS provider.', 'type' => 'module', 'dependencies' => ['core', 'otp_verification', 'sms']],
    'email_otp' => ['name' => 'Email OTP Adapter', 'description' => 'Deliver OTP through enabled email providers.', 'type' => 'module', 'dependencies' => ['core', 'otp_verification', 'email']],
    'gmail' => ['name' => 'Gmail / Google Workspace Adapter', 'description' => 'Google OAuth/API email delivery connector, separate from generic email.', 'type' => 'module', 'dependencies' => ['core', 'email']],

    // Extensible third-party connections.
    'integrations' => ['name' => 'External Integrations', 'description' => 'Allow-listed Google, Meta and third-party connectors.', 'type' => 'module', 'dependencies' => ['core']],
];
