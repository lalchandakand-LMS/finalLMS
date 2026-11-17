<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAttendanceAuditController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('admin.attendance-audit.index', [
            'entries' => $this->query($filters)->orderByDesc('logs.created_at')->paginate(50)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = $this->query($filters)->orderBy('logs.id');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Timestamp', 'Student', 'Student email', 'Batch', 'Attendance date', 'Actor', 'Previous status', 'New status', 'Source', 'Reason']);

            $query->chunk(500, function ($rows) use ($output): void {
                foreach ($rows as $row) {
                    $values = [
                        $row->created_at,
                        $row->student_name,
                        $row->student_email,
                        $row->batch_title,
                        $row->attendance_date,
                        $row->actor_name,
                        $row->previous_status,
                        $row->new_status,
                        $row->source,
                        $row->reason,
                    ];
                    fputcsv($output, array_map(fn ($value) => $this->csvSafe((string) ($value ?? '')), $values));
                }
            });

            fclose($output);
        }, 'attendance-audit-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'student_id' => ['nullable', 'integer', 'min:1'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'batch_id' => ['nullable', 'integer', 'min:1'],
            'source' => ['nullable', 'string', 'max:32'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function query(array $filters): Builder
    {
        $query = DB::table('attendance_change_logs as logs')
            ->join('attendances as attendance', 'attendance.id', '=', 'logs.attendance_id')
            ->join('users as student', 'student.id', '=', 'attendance.student_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'logs.actor_id')
            ->leftJoin('batches as batch', 'batch.id', '=', 'attendance.batch_id')
            ->select([
                'logs.id',
                'logs.created_at',
                'logs.previous_status',
                'logs.new_status',
                'logs.reason',
                'logs.source',
                'attendance.attendance_date',
                'attendance.student_id',
                'attendance.batch_id',
                'logs.actor_id',
                'student.name as student_name',
                'student.email as student_email',
                'actor.name as actor_name',
                'batch.title as batch_title',
            ]);

        if (! empty($filters['from'])) {
            $query->whereDate('logs.created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('logs.created_at', '<=', $filters['to']);
        }
        foreach (['student_id' => 'attendance.student_id', 'actor_id' => 'logs.actor_id', 'batch_id' => 'attendance.batch_id', 'source' => 'logs.source'] as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $query->where($column, $filters[$key]);
            }
        }
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $nested) use ($term): void {
                $nested->where('student.name', 'like', $term)
                    ->orWhere('student.email', 'like', $term)
                    ->orWhere('actor.name', 'like', $term)
                    ->orWhere('logs.reason', 'like', $term);
            });
        }

        return $query;
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
    }
}
