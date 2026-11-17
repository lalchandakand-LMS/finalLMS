<?php

namespace Aloms\StudentTracking;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\PluginInstallation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Throwable;

final class StudentTrackingSettingsController
{
    public function index(Request $request): View
    {
        abort_unless($request->user() instanceof User
            && $request->user()->isAdmin()
            && $this->hasGrantedPermission(), 403);

        return view('student-tracking::settings', [
            'lowAttendancePercent' => (int) Setting::value('student_tracking_low_attendance_percent', 75),
            'minimumAttendanceRecords' => (int) Setting::value('student_tracking_minimum_attendance_records', 5),
            'inactiveDays' => (int) Setting::value('student_tracking_inactive_days', 14),
        ]);
    }

    private function hasGrantedPermission(): bool
    {
        $plugin = PluginInstallation::query()
            ->where('plugin_key', 'aec.student-tracking')
            ->where('status', 'active')
            ->first();

        $metadata = is_array($plugin?->metadata) ? $plugin->metadata : [];
        $granted = $metadata['granted_permissions'] ?? [];

        return is_array($granted) && in_array('student-tracking.configure', $granted, true);
    }

    public function update(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->isAdmin() && $this->hasGrantedPermission(), 403);

        $values = $request->validate([
            'low_attendance_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'minimum_attendance_records' => ['required', 'integer', 'min:1', 'max:30'],
            'inactive_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        try {
            DB::transaction(function () use ($actor, $values, $request): void {
                Setting::setValue('student_tracking_low_attendance_percent', $values['low_attendance_percent']);
                Setting::setValue('student_tracking_minimum_attendance_records', $values['minimum_attendance_records']);
                Setting::setValue('student_tracking_inactive_days', $values['inactive_days']);

                ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => 'student_tracking.risk_rules_updated',
                'description' => 'Student tracking risk thresholds updated; no student records were changed.',
                'method' => 'PUT',
                'route' => $request->route()?->getName() ?? 'admin.student-tracking.settings.update',
                'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                ]);
            });
        } catch (Throwable $exception) {
            throw new \RuntimeException('Student tracking settings update could not be audited.', previous: $exception);
        }

        return back()->with('success', __('Student tracking risk rules updated.'));
    }
}
