<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->seedHomepageSettings();

            return;
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@lms.test'],
            ['name' => 'System Admin', 'password' => bcrypt('password'), 'role' => 'admin', 'email_verified_at' => now()]
        );

        $teacher = User::firstOrCreate(
            ['email' => 'teacher@lms.test'],
            ['name' => 'English Teacher', 'password' => bcrypt('password'), 'role' => 'teacher', 'email_verified_at' => now()]
        );

        $student = User::firstOrCreate(
            ['email' => 'student@lms.test'],
            ['name' => 'Student Learner', 'password' => bcrypt('password'), 'role' => 'student', 'email_verified_at' => now()]
        );

        foreach ([$admin, $teacher, $student] as $demoUser) {
            if (! $demoUser->hasVerifiedEmail()) {
                $demoUser->markEmailAsVerified();
            }
        }

        $courseOne = Course::firstOrCreate(
            ['slug' => 'introduction-to-english'],
            [
                'teacher_id' => $teacher->id,
                'title' => 'Introduction to English',
                'description' => 'A beginner-friendly English course covering vocabulary, speaking, and confidence.',
                'price' => 49.99,
                'status' => 'published',
            ]
        );

        $courseTwo = Course::firstOrCreate(
            ['slug' => 'business-communication'],
            [
                'teacher_id' => $teacher->id,
                'title' => 'Business Communication',
                'description' => 'Learn professional communication, presentations, and workplace English.',
                'price' => 89.00,
                'status' => 'published',
            ]
        );

        $enrollment = Enrollment::firstOrCreate(
            ['user_id' => $student->id, 'course_id' => $courseOne->id],
            ['status' => 'completed', 'completed_at' => now()]
        );

        Certificate::firstOrCreate(
            ['user_id' => $student->id, 'course_id' => $courseOne->id],
            [
                'certificate_number' => 'LMS-' . strtoupper(Str::random(8)),
                'status' => 'issued',
                'issued_at' => now(),
            ]
        );

        $this->seedHomepageSettings();
    }

    private function seedHomepageSettings(): void
    {
        $defaultHomeSettings = [
            'home_title' => 'Grow your skills with a complete LMS experience.',
            'home_subtitle' => 'Learn from expert-led courses, track your progress, enroll in structured programs, and earn certificates that help you move forward with confidence.',
            'home_cta_primary' => 'Browse courses',
            'home_cta_secondary' => 'Login',
            'home_featured_title' => 'Popular learning tracks',
            'home_reason_title' => 'Built to keep you moving forward',
            'home_metric_1_value' => '12+',
            'home_metric_1_label' => 'Active courses',
            'home_metric_2_value' => '4.9/5',
            'home_metric_2_label' => 'Learner score',
            'home_metric_3_value' => '24/7',
            'home_metric_3_label' => 'Support',
        ];

        foreach ($defaultHomeSettings as $key => $value) {
            Setting::setValue($key, $value);
        }
    }
}
