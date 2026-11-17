<?php

namespace Aloms\\UniversalAiAssistant;

use App\\Models\\ActivityLog;
use App\\Models\\Course;
use App\\Models\\Enrollment;
use App\\Models\\User;
use App\\Services\\Plugins\\PluginResourceAccess;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Throwable;

final class AssistantContextRetriever
{
    public function retrieve(User $actor, string $role, ?Request $request = null): ?string
    {
        $settings = AssistantSettings::record();
        if (! in_array($role, AssistantSettings::ROLES, true)
            || ! ($settings['role_data_access'][$role] ?? false)
            || AssistantSettings::roleForUser((string) $actor->role) !== $role) {
            return null;
        }

        $access = app(PluginResourceAccess::class);
        $courses = $access->scopeQuery(Course::query(), $actor)
            ->select(['id', 'title', 'status'])
            ->orderBy('title')
            ->limit(25)
            ->get();

        $courseIds = $courses->modelKeys();
        $payload = [
            'scope' => $role,
            'access' => 'read_only',
            'courses' => $courses->map(static fn (Course $course): array => [
                'title' => (string) $course->title,
                'status' => (string) $course->status,
            ])->values()->all(),
        ];

        if ($actor->isStudent() || $actor->isGuardian()) {
            $enrollments = $access->scopeQuery(Enrollment::query(), $actor)
                ->with('course:id,title')
                ->select(['id', 'course_id', 'user_id', 'status', 'created_at'])
                ->latest('created_at')
                ->limit(30)
                ->get();

            $payload['my_or_linked_enrollments'] = $enrollments->map(static fn (Enrollment $enrollment): array => [
                'course' => (string) ($enrollment->course?->title ?? ''),
                'status' => (string) $enrollment->status,
            ])->values()->all();
            $enrollmentCount = $enrollments->count();
        } else {
            $counts = $courseIds === [] ? collect() : $access->scopeQuery(Enrollment::query(), $actor)
                ->whereIn('course_id', $courseIds)
                ->select(['course_id', 'status', DB::raw('COUNT(*) as total')])
                ->groupBy('course_id', 'status')
                ->get();

            $grouped = $counts->groupBy('course_id');
            $payload['enrollment_summary'] = $courses->map(static function (Course $course) use ($grouped): array {
                return [
                    'course' => (string) $course->title,
                    'enrollments_by_status' => $grouped->get($course->id, collect())
                        ->mapWithKeys(static fn ($row): array => [(string) $row->status => (int) $row->total])
                        ->all(),
                ];
            })->values()->all();
            $enrollmentCount = (int) $counts->sum('total');
        }

        $this->auditRetrieval($actor, $role, $courses->count(), $enrollmentCount, $request);

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function auditRetrieval(User $actor, string $role, int $courseCount, int $enrollmentCount, ?Request $request): void
    {
        try {
            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => 'ai_assistant.scoped_context_retrieved',
                'description' => 'Read-only AI context retrieved for role '.$role.'; courses='.$courseCount.'; scoped enrollments='.$enrollmentCount.'. No record content was copied into the audit log.',
                'method' => $request?->method() ?? 'POST',
                'route' => $request?->route()?->getName() ?? 'ai-assistant.context',
                'ip_address' => $request?->ip(),
                'user_agent' => substr((string) ($request?->userAgent() ?? ''), 0, 1000),
            ]);
        } catch (Throwable) {
            // Context retrieval remains bounded; audit failures are reported without logging retrieved content.
            report(new \\RuntimeException('Unable to persist AI scoped-context access audit record.'));
        }
    }
}
