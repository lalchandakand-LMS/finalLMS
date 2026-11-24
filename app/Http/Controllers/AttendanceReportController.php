<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->query($request->user(), $filters);
        $summaryQuery = clone $query;

        return view('attendance.reports.index', [
            'records' => $query->with(['student:id,name,email', 'batch:id,title,course_id,branch_id', 'batch.course:id,title', 'batch.branch:id,name'])
                ->orderByDesc('attendance_date')->orderBy('batch_id')->paginate(50)->withQueryString(),
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'present' => (clone $summaryQuery)->where('status', 'present')->count(),
                'late' => (clone $summaryQuery)->where('status', 'late')->count(),
                'absent' => (clone $summaryQuery)->where('status', 'absent')->count(),
                'excused' => (clone $summaryQuery)->where('status', 'excused')->count(),
            ],
            'filters' => $filters,
            'batches' => $this->availableBatches($request),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = $this->query($request->user(), $filters)
            ->with(['student:id,name,email', 'batch:id,title,course_id,branch_id', 'batch.course:id,title', 'batch.branch:id,name'])
            ->orderBy('attendance_date')->orderBy('batch_id')->orderBy('student_id');

        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'event' => 'attendance.report.exported',
            'description' => 'Attendance report CSV exported; filters='.json_encode($filters, JSON_THROW_ON_ERROR),
            'method' => 'GET',
            'route' => $request->route()?->getName() ?? 'attendance-reports.export',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Student', 'Email', 'Course', 'Batch', 'Branch', 'Status']);
            $query->chunk(500, function ($rows) use ($output): void {
                foreach ($rows as $row) {
                    $values = [
                        $row->attendance_date?->format('Y-m-d'),
                        $row->student?->name,
                        $row->student?->email,
                        $row->batch?->course?->title,
                        $row->batch?->title,
                        $row->batch?->branch?->name,
                        $row->status,
                    ];
                    fputcsv($output, array_map(fn ($value) => $this->csvSafe((string) ($value ?? '')), $values));
                }
            });
            fclose($output);
        }, 'attendance-report-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', 'in:present,absent,late,excused'],
            'batch_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function query($actor, array $filters): Builder
    {
        $query = Attendance::query()->with('batch');
        if ($actor->isTeacher()) {
            $query->whereHas('batch', fn (Builder $batch) => $batch->where('teacher_id', $actor->id));
        } elseif ($actor->isBranchManager()) {
            abort_unless($actor->branch_id && DB::table('branches')->where('id', $actor->branch_id)->where('is_active', true)->exists(), 403);
            abort_unless($actor->hasAdminPermission('branch.attendance'), 403);
            $query->whereHas('batch', fn (Builder $batch) => $batch->where('branch_id', $actor->branch_id));
        } else {
            abort_unless($actor->isAdmin(), 403);
        }

        if (! empty($filters['from'])) $query->whereDate('attendance_date', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('attendance_date', '<=', $filters['to']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['batch_id'])) $query->where('batch_id', $filters['batch_id']);
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->whereHas('student', fn (Builder $student) => $student
                ->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }

        return $query;
    }

    private function availableBatches(Request $request)
    {
        $query = \App\Models\Batch::query()->orderBy('title');
        if ($request->user()->isTeacher()) $query->where('teacher_id', $request->user()->id);
        elseif ($request->user()->isBranchManager()) $query->where('branch_id', $request->user()->branch_id);
        return $query->get(['id', 'title']);
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
    }
}