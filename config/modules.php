<?php

return [
    'core' => ['name' => 'Core Platform', 'description' => 'Authentication, authorization, tenancy and shared infrastructure.', 'type' => 'core', 'dependencies' => [], 'protected' => true],
    'identity' => ['name' => 'Identity & Access', 'description' => 'Users, roles, MFA, trusted devices and sessions.', 'type' => 'module', 'dependencies' => ['core'], 'protected' => true],
    'academics' => ['name' => 'Academic Management', 'description' => 'Courses, lessons, batches, assignments, exams and attendance.', 'type' => 'module', 'dependencies' => ['core', 'identity']],
    'student_portal' => ['name' => 'Student & Guardian Portal', 'description' => 'Learning portal, guardian access and progress.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'academics']],
    'finance' => ['name' => 'Finance & Billing', 'description' => 'Invoices, fees, expenses, refunds and reports.', 'type' => 'module', 'dependencies' => ['core', 'identity']],
    'payments' => ['name' => 'Payment Services', 'description' => 'Payment orchestration and provider adapters.', 'type' => 'module', 'dependencies' => ['core', 'finance']],
    'communications' => ['name' => 'Communications', 'description' => 'SMS, email, verification and notifications.', 'type' => 'module', 'dependencies' => ['core', 'identity']],
    'notice_board' => ['name' => 'Notice Board', 'description' => 'Announcements, audience targeting and read receipts.', 'type' => 'module', 'dependencies' => ['core', 'identity']],
    'marketing' => ['name' => 'Marketing & Growth', 'description' => 'SEO, Meta Pixel, campaign planning and analytics.', 'type' => 'module', 'dependencies' => ['core']],
    'content_store' => ['name' => 'Content & eBook Store', 'description' => 'eBooks, digital products, coupons and storefront.', 'type' => 'module', 'dependencies' => ['core', 'payments']],
    'reporting' => ['name' => 'Analytics & Reporting', 'description' => 'Operational, academic and financial reports.', 'type' => 'module', 'dependencies' => ['core']],
    'storage' => ['name' => 'Storage & Backups', 'description' => 'Cloud storage, backups and media management.', 'type' => 'module', 'dependencies' => ['core']],
    'website_cms' => ['name' => 'Website & CMS', 'description' => 'Website builder, pages, blog and public content.', 'type' => 'module', 'dependencies' => ['core']],
    'mobile_api' => ['name' => 'Mobile App API', 'description' => 'Student and instructor application APIs.', 'type' => 'module', 'dependencies' => ['core', 'identity', 'academics']],
    'integrations' => ['name' => 'External Integrations', 'description' => 'Google, Meta and third-party service connectors.', 'type' => 'module', 'dependencies' => ['core']],
];
