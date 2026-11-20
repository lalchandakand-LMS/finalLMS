<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\DB;

final class PendingCoursePaymentGuard
{
    /**
     * Return true when a pending direct payment or pending combo payment
     * already reserves any of the requested courses for this student.
     *
     * @param list<int> $courseIds
     */
    public function hasPendingForCourses(int $studentId, array $courseIds): bool
    {
        $courseIds = array_values(array_unique(array_filter(
            array_map('intval', $courseIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($courseIds === []) {
            return false;
        }

        $hasPendingDirectPayment = DB::table('payments')
            ->where('user_id', $studentId)
            ->whereIn('course_id', $courseIds)
            ->whereNull('course_combo_id')
            ->whereNull('order_id')
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingDirectPayment) {
            return true;
        }

        return DB::table('payment_combo_courses')
            ->join('payments', 'payments.id', '=', 'payment_combo_courses.payment_id')
            ->where('payments.user_id', $studentId)
            ->whereIn('payment_combo_courses.course_id', $courseIds)
            ->where('payments.status', 'pending')
            ->exists();
    }

    public function hasPendingForCourse(int $studentId, int $courseId): bool
    {
        return $this->hasPendingForCourses($studentId, [$courseId]);
    }
}
