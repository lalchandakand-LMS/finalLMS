<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\GuardianStudentLink;
use App\Models\Invoice;
use App\Models\LessonProgress;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuardianController extends Controller
{
    public function dashboard(Request $request)
    {
        $guardian = $request->user();
        abort_unless($guardian?->isGuardian(), 403);
        $this->requireActiveBranch($guardian);

        $links = GuardianStudentLink::query()
            ->where('guardian_id', $guardian->id)
            ->whereIn('status', ['pending', 'active'])
            ->latest('requested_at')
            ->get();

        return view('guardian.dashboard', compact('links'));
    }

    public function requestsPage(Request $request)
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);
        $this->requireActiveBranch($student);

        $requests = GuardianStudentLink::query()
            ->where('student_id', $student->id)
            ->where('status', 'pending')
            ->with('guardian:id,name,email')
            ->latest('requested_at')
            ->get();

        return view('student.guardian-requests.index', compact('requests'));
    }

    public function links(Request $request): JsonResponse
    {
        $guardian = $request->user();
        abort_unless($guardian?->isGuardian(), 403);
        $this->requireActiveBranch($guardian);

        $rows = GuardianStudentLink::query()
            ->where('guardian_id', $guardian->id)
            ->whereIn('status', ['pending', 'active'])
            ->latest('requested_at')
            ->get(['id', 'guardian_id', 'student_id', 'relationship', 'status', 'requested_at', 'reviewed_at']);

        return response()->json([
            'data' => $rows->map(fn (GuardianStudentLink $link): array => [
                'id' => $link->id,
                'relationship' => $link->relationship,
                'status' => $link->status,
                'requested_at' => $link->requested_at,
                'reviewed_at' => $link->reviewed_at,
                'student' => $link->status === 'active'
                    ? $link->student?->only(['id', 'name', 'email'])
                    : null,
            ]),
        ]);
    }

    public function requestLink(Request $request): JsonResponse
    {
        $guardian = $request->user();
        abort_unless($guardian?->isGuardian(), 403);
        $this->requireActiveBranch($guardian);

        $data = $request->validate([
            'student_email' => ['required', 'email:rfc', 'max:190'],
            'relationship' => ['nullable', 'string', 'min:1', 'max:60'],
        ]);

        $student = User::query()
            ->where('email', strtolower(trim($data['student_email'])))
            ->where('role', 'student')
            ->whereNotNull('email_verified_at')
            ->first();

        // Do not disclose whether an email belongs to a verified student or
        // whether that student is in another branch. Pending links reveal no
        // student identity until the student explicitly approves the request.
        $genericResponse = fn () => response()->json([
            'message' => 'If a verified student account matches that email, a request has been sent.',
        ], 202);

        if (! $student || ! $this->canLinkSameBranch($guardian, $student)) {
            return $genericResponse();
        }

        $link = DB::transaction(function () use ($request, $guardian, $student, $data): GuardianStudentLink {
            User::query()->whereKey($guardian->id)->lockForUpdate()->firstOrFail();

            $existing = GuardianStudentLink::query()
                ->where('guardian_id', $guardian->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($existing && in_array($existing->status, ['pending', 'active'], true)) {
                return $existing;
            }

            $link = $existing ?: new GuardianStudentLink();
            $link->fill([
                'guardian_id' => $guardian->id,
                'student_id' => $student->id,
                'relationship' => trim((string) ($data['relationship'] ?? 'Guardian')) ?: 'Guardian',
                'status' => 'pending',
                'requested_by' => $guardian->id,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);
            $link->save();

            $this->audit($request, $guardian, 'guardian_link.requested', $link, 'Guardian requested a student relationship.');

            return $link;
        });

        return $genericResponse();
    }

    public function incomingRequests(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);
        $this->requireActiveBranch($student);

        $rows = GuardianStudentLink::query()
            ->where('student_id', $student->id)
            ->where('status', 'pending')
            ->with('guardian:id,name,email')
            ->latest('requested_at')
            ->get(['id', 'guardian_id', 'student_id', 'relationship', 'status', 'requested_at']);

        return response()->json(['data' => $rows]);
    }

    public function respond(Request $request, GuardianStudentLink $link): JsonResponse
    {
        $student = $request->user();
        abort_unless($student?->isStudent() && (int) $link->student_id === (int) $student->id, 404);
        $this->requireActiveBranch($student);

        $data = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
        ]);

        DB::transaction(function () use ($request, $student, $link, $data): void {
            $row = GuardianStudentLink::query()->whereKey($link->id)->lockForUpdate()->firstOrFail();

            if ($row->status !== 'pending') {
                abort(409);
            }

            $guardian = User::query()->findOrFail($row->guardian_id);
            $this->sameBranch($guardian, $student);

            $row->respondByStudent($student, $data['decision'] === 'accept');
            $this->audit(
                $request,
                $student,
                $data['decision'] === 'accept' ? 'guardian_link.accepted' : 'guardian_link.rejected',
                $row,
                'Student responded to a guardian request.'
            );
        });

        return response()->json([
            'data' => ['id' => $link->id, 'status' => $data['decision'] === 'accept' ? 'active' : 'rejected'],
        ]);
    }

    public function revoke(Request $request, GuardianStudentLink $link): JsonResponse
    {
        $actor = $request->user();
        abort_unless(
            $actor && (
                ($actor->isGuardian() && (int) $link->guardian_id === (int) $actor->id)
                || ($actor->isStudent() && (int) $link->student_id === (int) $actor->id)
            ),
            404
        );
        $this->requireActiveBranch($actor);

        DB::transaction(function () use ($request, $actor, $link): void {
            $row = GuardianStudentLink::query()->whereKey($link->id)->lockForUpdate()->firstOrFail();
            $row->revokeBy($actor);
            $this->audit($request, $actor, 'guardian_link.revoked', $row, 'Guardian/student revoked the relationship.');
        });

        return response()->json(['data' => ['id' => $link->id, 'status' => 'revoked']]);
    }

    public function summary(Request $request, User $student): JsonResponse
    {
        $guardian = $request->user();
        abort_unless($guardian?->isGuardian(), 403);
        $this->requireActiveBranch($guardian);

        $link = GuardianStudentLink::query()
            ->where('guardian_id', $guardian->id)
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->whereNotNull('reviewed_at')
            ->first();

        abort_unless($link && $student->isStudent(), 404);
        $this->sameBranch($guardian, $student);

        return response()->json(['data' => [
            'student' => $student->only(['id', 'name', 'email']),
            'courses' => Enrollment::query()->where('user_id', $student->id)->with('course:id,title')->latest()->limit(30)->get(['id', 'course_id', 'status', 'completed_at', 'created_at']),
            'progress' => LessonProgress::query()->where('user_id', $student->id)->whereNotNull('completed_at')->with('lesson:id,course_id,title')->latest('completed_at')->limit(50)->get(['id', 'lesson_id', 'completed_at']),
            'attendance' => Attendance::query()->where('student_id', $student->id)->with('batch.course:id,title')->orderByDesc('attendance_date')->limit(30)->get(['id', 'batch_id', 'attendance_date', 'status']),
            'exams' => ExamAttempt::query()->where('student_id', $student->id)->where('status', 'submitted')->with('exam:id,batch_id,title')->latest('submitted_at')->limit(30)->get(['id', 'exam_id', 'score', 'status', 'submitted_at']),
            'payments' => Payment::query()->where('user_id', $student->id)->with('course:id,title')->latest()->limit(30)->get(['id', 'course_id', 'amount', 'currency', 'method', 'status', 'created_at']),
            'invoices' => Invoice::query()->where('user_id', $student->id)->with('course:id,title')->withSum('payments', 'amount')->latest()->limit(30)->get(['id', 'course_id', 'invoice_number', 'title', 'amount', 'due_date', 'status']),
            'certificates' => Certificate::query()->where('user_id', $student->id)->with('course:id,title')->latest('issued_at')->limit(30)->get(['id', 'course_id', 'certificate_number', 'status', 'issued_at']),
        ]]);
    }

    private function requireActiveBranch(User $user): void
    {
        if ($user->branch_id !== null && ! Branch::query()->whereKey($user->branch_id)->where('is_active', true)->exists()) {
            abort(403, 'The assigned branch is inactive.');
        }
    }

    private function canLinkSameBranch(User $guardian, User $student): bool
    {
        if (($guardian->branch_id === null) !== ($student->branch_id === null)
            || ($guardian->branch_id !== null && (int) $guardian->branch_id !== (int) $student->branch_id)) {
            return false;
        }

        return $student->branch_id === null
            || Branch::query()->whereKey($student->branch_id)->where('is_active', true)->exists();
    }

    private function sameBranch(User $guardian, User $student): void
    {
        if (($guardian->branch_id === null) !== ($student->branch_id === null)
            || ($guardian->branch_id !== null && (int) $guardian->branch_id !== (int) $student->branch_id)) {
            abort(403, 'Guardian and student must belong to the same branch.');
        }

        $this->requireActiveBranch($guardian);
        $this->requireActiveBranch($student);
    }

    private function audit(Request $request, User $actor, string $event, GuardianStudentLink $link, string $description): void
    {
        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => $event,
            'description' => substr($description.' Link #'.$link->id, 0, 255),
            'method' => strtoupper($request->method()),
            'route' => substr($request->route()?->getName() ?? $request->path(), 0, 190),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
