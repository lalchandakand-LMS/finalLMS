<?php

declare(strict_types=1);

namespace Aloms\AdmissionManagement;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PluginInstallation;
use App\Models\User;
use App\Services\Plugins\PluginSettingsManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdmissionController extends Controller
{
    private const TRANSITIONS = [
        'pending' => ['screening', 'shortlisted', 'approved', 'rejected', 'waitlisted'],
        'screening' => ['shortlisted', 'approved', 'rejected', 'waitlisted'],
        'shortlisted' => ['approved', 'rejected', 'waitlisted'],
        'waitlisted' => ['screening', 'shortlisted', 'approved', 'rejected'],
        'approved' => [],
        'rejected' => [],
    ];

    private const DOCUMENT_TYPES = ['photo', 'birth_certificate', 'nid', 'previous_certificate', 'other'];

    public function publicForm(): View
    {
        return view('admission-management::public.apply', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'documentTypes' => self::DOCUMENT_TYPES,
        ]);
    }

    public function applyPublic(Request $request, PluginSettingsManager $settings): RedirectResponse
    {
        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.admission-management')->firstOrFail();
        abort_if(strtolower((string) ($settings->get($plugin, 'intake_status') ?? 'open')) !== 'open', 403, 'Admission applications are currently closed.');

        $data = $this->validatedApplicant($request);
        $files = $this->validatedDocuments($request);
        $data['email'] = strtolower(trim($data['email']));
        $data['application_source'] = 'website';
        $data['status'] = 'pending';
        $data['document_verification_status'] = $files === [] ? 'missing' : 'pending';
        $data['application_code'] = $this->newApplicationCode();
        $storedPaths = [];

        try {
            $application = DB::transaction(function () use ($data, $files, $request, &$storedPaths): AdmissionApplication {
                $application = AdmissionApplication::query()->create($data);
                $this->recordEvent($application, null, 'application_submitted', null, 'pending', 'Online admission application submitted.');

                foreach ($files as $type => $file) {
                    $storedPaths[] = $this->persistDocument($application, $file, (string) $type, null);
                }

                return $application;
            }, 3);
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $storedPath) Storage::disk('local')->delete($storedPath);
            throw $exception;
        }

        return redirect()->route('admission.public.track')
            ->with('status', 'Application submitted. Keep your tracking ID: '.$application->application_code);
    }

    public function trackForm(): View
    {
        return view('admission-management::public.track', ['application' => null, 'searched' => false]);
    }

    public function track(Request $request): View
    {
        $data = $request->validate([
            'application_code' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email:rfc', 'max:160'],
        ]);
        $application = AdmissionApplication::query()
            ->where('application_code', strtoupper(trim($data['application_code'])))
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))])
            ->with(['documents:id,admission_application_id,document_type,review_status,created_at'])
            ->first(['id', 'application_code', 'applicant_name', 'desired_course', 'status', 'document_verification_status', 'created_at', 'reviewed_at', 'interview_at']);

        return view('admission-management::public.track', ['application' => $application, 'searched' => true]);
    }

    public function index(Request $request): View
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $filters = $this->validatedReportFilters($request);
        $search = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $query = $this->filteredApplications($actor, $filters)
            ->when(! empty($search['q']), function (Builder $q) use ($search): void {
                $term = trim($search['q']);
                $q->where(fn (Builder $nested) => $nested
                    ->where('applicant_name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhere('phone', 'like', '%'.$term.'%')
                    ->orWhere('application_code', 'like', '%'.$term.'%')
                    ->orWhere('desired_course', 'like', '%'.$term.'%'));
            })
            ->when($request->filled('document_status'), fn (Builder $q) => $q->where('document_verification_status', $request->input('document_status')));

        return view('admission-management::admin.index', [
            'applications' => $query->with(['documents.reviewer', 'assignee', 'events.actor', 'branch'])->latest()->paginate(20)->withQueryString(),
            'statusCounts' => $this->scopedApplications($actor)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'statuses' => array_keys(self::TRANSITIONS),
            'documentTypes' => self::DOCUMENT_TYPES,
            'branches' => $actor->isBranchManager() ? collect() : Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'reviewers' => User::query()->whereIn('role', ['admin', 'branch_manager', 'teacher'])
                ->when($actor->isBranchManager(), fn (Builder $q) => $q->where('branch_id', $actor->branch_id))
                ->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $data = $this->validatedApplicant($request);
        $data['email'] = strtolower(trim($data['email']));
        $data['application_source'] = 'walk_in';
        $data['status'] = 'pending';
        $data['document_verification_status'] = 'missing';
        $data['application_code'] = $this->newApplicationCode();
        $data['created_by'] = $actor->id;
        if ($actor->isBranchManager()) $data['branch_id'] = $actor->branch_id;

        $application = DB::transaction(function () use ($actor, $data): AdmissionApplication {
            $application = AdmissionApplication::query()->create($data);
            $this->recordEvent($application, $actor->id, 'application_created', null, 'pending', 'Walk-in application created by staff.');
            return $application;
        }, 3);

        return back()->with('success', 'Application '.$application->application_code.' created.');
    }

    public function review(Request $request, int $application): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::TRANSITIONS))],
            'reviewer_notes' => [$request->input('status') === 'rejected' ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
            'interview_at' => ['nullable', 'date', 'after_or_equal:now'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($actor, $application, $data): void {
            $record = AdmissionApplication::query()->lockForUpdate()->findOrFail($application);
            $this->assertApplicationBranch($actor, $record);
            $next = $data['status'];
            abort_unless($next === $record->status || in_array($next, self::TRANSITIONS[$record->status] ?? [], true), 422, 'Invalid admission status transition.');
            if ($next === 'approved' && $record->documents()->exists()) {
                abort_unless($record->documents()->where('review_status', '!=', 'verified')->doesntExist(), 422, 'Verify or resolve every submitted document before approving this application.');
            }
            if (! empty($data['assigned_to'])) {
                $assignee = User::query()->findOrFail($data['assigned_to']);
                abort_unless(in_array($assignee->role, ['admin', 'branch_manager', 'teacher'], true), 422, 'Invalid reviewer role.');
                if ($assignee->role === 'branch_manager' || $assignee->branch_id !== null) {
                    abort_unless((int) $assignee->branch_id === (int) $record->branch_id, 422, 'Reviewer must belong to the applicant branch.');
                }
                if ($actor->isBranchManager()) abort_unless((int) $assignee->branch_id === (int) $actor->branch_id, 403);
            }

            $previous = $record->status;
            $record->forceFill([
                'status' => $next,
                'reviewer_notes' => $data['reviewer_notes'] ?? $record->reviewer_notes,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'interview_at' => $data['interview_at'] ?? $record->interview_at,
                'assigned_to' => $data['assigned_to'] ?? $record->assigned_to,
            ])->save();

            $this->recordEvent($record, $actor->id, 'application_reviewed', $previous, $next, $data['reviewer_notes'] ?? null, [
                'interview_at' => isset($data['interview_at']) ? (string) $data['interview_at'] : null,
                'assigned_to' => $data['assigned_to'] ?? null,
            ]);
        }, 3);

        return back()->with('success', 'Admission application updated.');
    }

    public function storeDocument(Request $request, int $application): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $record = AdmissionApplication::query()->findOrFail($application);
        $this->assertApplicationBranch($actor, $record);
        $data = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'document_type' => ['nullable', Rule::in(self::DOCUMENT_TYPES)],
        ]);
        $path = $this->persistDocument($record, $data['document'], $data['document_type'] ?? 'other', $actor->id);
        $this->recalculateDocumentStatus($record);
        $this->recordEvent($record, $actor->id, 'document_uploaded', null, null, 'A document was uploaded by staff.', [
            'document_type' => $data['document_type'] ?? 'other',
        ]);

        return back()->with('success', 'Private document uploaded for verification.');
    }

    public function reviewDocument(Request $request, int $application, int $document): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $data = $request->validate([
            'review_status' => ['required', Rule::in(['verified', 'rejected'])],
            'review_notes' => [$request->input('review_status') === 'rejected' ? 'required' : 'nullable', 'string', 'min:5', 'max:1500'],
        ]);

        DB::transaction(function () use ($actor, $application, $document, $data): void {
            $record = AdmissionApplication::query()->lockForUpdate()->findOrFail($application);
            $this->assertApplicationBranch($actor, $record);
            $file = $record->documents()->lockForUpdate()->findOrFail($document);
            $file->forceFill([
                'review_status' => $data['review_status'],
                'review_notes' => $data['review_notes'] ?? null,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            $previous = $record->document_verification_status;
            $this->recalculateDocumentStatus($record);
            $record->refresh();
            $this->recordEvent($record, $actor->id, 'document_reviewed', $previous, $record->document_verification_status, $data['review_notes'] ?? null, [
                'document_id' => $file->id,
                'document_type' => $file->document_type,
                'document_review_status' => $file->review_status,
            ]);
        }, 3);

        return back()->with('success', 'Document review saved.');
    }

    public function downloadDocument(Request $request, int $application, int $document): StreamedResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $record = AdmissionApplication::query()->findOrFail($application);
        $this->assertApplicationBranch($actor, $record);
        $file = $record->documents()->findOrFail($document);
        abort_unless($file->disk === 'local' && str_starts_with($file->path, 'admission-documents/'.$record->id.'/'), 404);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function reports(Request $request): View
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $filters = $this->validatedReportFilters($request);
        $query = $this->filteredApplications($actor, $filters);
        $statusCounts = (clone $query)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $sourceCounts = (clone $query)->selectRaw('application_source, COUNT(*) as aggregate')->groupBy('application_source')->pluck('aggregate', 'application_source');
        $documentCounts = (clone $query)->selectRaw('document_verification_status, COUNT(*) as aggregate')->groupBy('document_verification_status')->pluck('aggregate', 'document_verification_status');
        $total = (clone $query)->count();
        $converted = (clone $query)->whereNotNull('converted_student_id')->count();
        $monthlyExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";
        $monthly = (clone $query)->selectRaw($monthlyExpression.' as month, COUNT(*) as total')
            ->groupBy('month')->orderBy('month')->limit(18)->get();

        return view('admission-management::admin.reports', [
            'filters' => $filters,
            'total' => $total,
            'converted' => $converted,
            'conversionRate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0,
            'statusCounts' => $statusCounts,
            'sourceCounts' => $sourceCounts,
            'documentCounts' => $documentCounts,
            'monthly' => $monthly,
            'branches' => $actor->isBranchManager() ? collect() : Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $filters = $this->validatedReportFilters($request);
        $query = $this->filteredApplications($actor, $filters)->with('branch:id,name')->orderBy('id');
        $filename = 'admission-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Application ID', 'Applicant', 'Email', 'Phone', 'Guardian Phone', 'Course', 'Preferred Batch', 'Branch', 'Source', 'Application Status', 'Document Verification', 'Submitted At', 'Reviewed At', 'Interview At', 'Converted Student ID']);
            $query->chunkById(500, function ($applications) use ($output): void {
                foreach ($applications as $application) {
                    fputcsv($output, [
                        $application->application_code,
                        $application->applicant_name,
                        $application->email,
                        $application->phone,
                        $application->guardian_phone,
                        $application->desired_course,
                        $application->preferred_batch,
                        $application->branch?->name,
                        $application->application_source,
                        $application->status,
                        $application->document_verification_status,
                        $application->created_at?->toDateTimeString(),
                        $application->reviewed_at?->toDateTimeString(),
                        $application->interview_at?->toDateTimeString(),
                        $application->converted_student_id,
                    ]);
                }
            });
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function convertToStudent(Request $request, int $application, PluginSettingsManager $settings): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.admission-management')->firstOrFail();
        $admissionFee = max(0, (int) ($settings->get($plugin, 'admission_fee_bdt') ?? 0));
        $conversion = DB::transaction(function () use ($actor, $application, $admissionFee): array {
            $record = AdmissionApplication::query()->lockForUpdate()->findOrFail($application);
            $this->assertApplicationBranch($actor, $record);
            abort_unless($record->status === 'approved', 422, 'Only approved applicants can be converted.');
            if ($record->documents()->exists()) {
                abort_unless($record->documents()->where('review_status', '!=', 'verified')->doesntExist(), 422, 'All submitted documents must be verified before conversion.');
            }
            if ($record->converted_student_id) return ['student_id' => (int) $record->converted_student_id, 'invoice_id' => $record->admission_fee_invoice_id ? (int) $record->admission_fee_invoice_id : null];
            abort_if(User::query()->where('email', strtolower($record->email))->exists(), 409, 'An account already uses this email. Review it manually before conversion.');

            $student = User::query()->create([
                'name' => $record->applicant_name,
                'email' => strtolower($record->email),
                'phone_number' => $record->phone,
                'role' => 'student',
                'branch_id' => $record->branch_id,
                'password' => Str::random(48),
            ]);
            $invoice = null;
            if ($admissionFee > 0) {
                $invoice = Invoice::query()->create([
                    'user_id' => $student->id,
                    'created_by' => $actor->id,
                    'invoice_number' => 'AEC-ADM-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                    'title' => 'Admission Fee - '.$record->desired_course,
                    'description' => 'Admission processing fee for application '.$record->application_code,
                    'amount' => $admissionFee,
                    'subtotal' => $admissionFee,
                    'discount_type' => null,
                    'discount_value' => 0,
                    'due_date' => now()->addDays(14)->toDateString(),
                    'status' => 'unpaid',
                ]);
            }
            $record->forceFill([
                'converted_student_id' => $student->id,
                'converted_at' => now(),
                'admission_fee_invoice_id' => $invoice?->id,
            ])->save();
            $this->recordEvent($record, $actor->id, 'student_converted', 'approved', 'converted', 'Applicant converted into a student account.', [
                'student_id' => $student->id,
                'invoice_id' => $invoice?->id,
            ]);

            return ['student_id' => (int) $student->id, 'invoice_id' => $invoice?->id];
        }, 3);

        $student = User::query()->findOrFail($conversion['student_id']);
        $status = Password::sendResetLink(['email' => $student->email]);
        Log::info('Admission conversion password setup requested.', [
            'application_id' => $application,
            'student_id' => $conversion['student_id'],
            'invoice_id' => $conversion['invoice_id'],
            'broker_status' => $status,
        ]);

        return back()->with('success', 'Student account created.'.($conversion['invoice_id'] ? ' Admission fee invoice #'.$conversion['invoice_id'].' created.' : '').' A password setup email was requested if mail is configured.');
    }

    private function validatedApplicant(Request $request): array
    {
        return $request->validate([
            'applicant_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'guardian_phone' => ['nullable', 'string', 'max:40'],
            'desired_course' => ['required', 'string', 'max:160'],
            'preferred_batch' => ['nullable', 'string', 'max:80'],
            'previous_education' => ['nullable', 'string', 'max:160'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:2000'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
        ]);
    }

    /** @return array<int, \Illuminate\Http\UploadedFile> */
    private function validatedDocuments(Request $request): array
    {
        $data = $request->validate([
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $files = array_filter($data['documents'] ?? [], fn ($file) => $file instanceof \Illuminate\Http\UploadedFile);
        foreach (array_keys($files) as $type) {
            abort_unless(in_array((string) $type, self::DOCUMENT_TYPES, true), 422, 'Invalid document type.');
        }

        return $files;
    }

    private function persistDocument(AdmissionApplication $application, \Illuminate\Http\UploadedFile $file, string $type, ?int $actorId): string
    {
        abort_unless(in_array($type, self::DOCUMENT_TYPES, true), 422, 'Invalid document type.');
        $extension = strtolower((string) $file->extension());
        abort_unless(in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true), 422, 'Unsupported document file.');
        $path = $file->storeAs('admission-documents/'.$application->id, Str::uuid().'.'.$extension, 'local');
        if (! is_string($path) || $path === '') throw new \RuntimeException('Could not store admission document privately.');

        try {
            $application->documents()->create([
                'uploaded_by' => $actorId,
                'disk' => 'local',
                'path' => $path,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'document_type' => $type,
                'review_status' => 'pending',
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return $path;
    }

    private function recalculateDocumentStatus(AdmissionApplication $application): void
    {
        $documents = $application->documents();
        if (! $documents->exists()) {
            $status = 'missing';
        } elseif ($documents->where('review_status', 'rejected')->exists()) {
            $status = 'revision_required';
        } elseif ($documents->where('review_status', '!=', 'verified')->exists()) {
            $status = 'pending';
        } else {
            $status = 'verified';
        }
        $application->forceFill(['document_verification_status' => $status])->save();
    }

    private function recordEvent(
        AdmissionApplication $application,
        ?int $actorId,
        string $eventKey,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $note = null,
        array $metadata = [],
    ): void {
        AdmissionApplicationEvent::query()->create([
            'admission_application_id' => $application->id,
            'actor_id' => $actorId,
            'event_key' => $eventKey,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedReportFilters(Request $request): array
    {
        return $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'status' => ['nullable', Rule::in(array_keys(self::TRANSITIONS))],
            'source' => ['nullable', Rule::in(['website', 'walk_in'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'course' => ['nullable', 'string', 'max:160'],
            'document_status' => ['nullable', Rule::in(['missing', 'pending', 'verified', 'revision_required'])],
        ]);
    }

    private function filteredApplications(User $actor, array $filters): Builder
    {
        return $this->scopedApplications($actor)
            ->when(! empty($filters['from_date']), fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['from_date']))
            ->when(! empty($filters['to_date']), fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['to_date']))
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['source']), fn (Builder $q) => $q->where('application_source', $filters['source']))
            ->when(! empty($filters['branch_id']) && ! $actor->isBranchManager(), fn (Builder $q) => $q->where('branch_id', $filters['branch_id']))
            ->when(! empty($filters['course']), fn (Builder $q) => $q->where('desired_course', 'like', '%'.$filters['course'].'%'))
            ->when(! empty($filters['document_status']), fn (Builder $q) => $q->where('document_verification_status', $filters['document_status']));
    }

    private function scopedApplications(User $actor): Builder
    {
        $query = AdmissionApplication::query();
        if ($actor->isBranchManager()) $query->where('branch_id', $actor->branch_id);
        return $query;
    }

    private function newApplicationCode(): string
    {
        do {
            $code = 'ADM-'.strtoupper(Str::random(10));
        } while (AdmissionApplication::query()->where('application_code', $code)->exists());

        return $code;
    }

    private function assertBranchAssigned(User $actor): void
    {
        if ($actor->isBranchManager()) {
            abort_unless($actor->branch_id !== null
                && DB::table('branches')->where('id', $actor->branch_id)->where('is_active', true)->exists(),
                403, 'Assign this account to an active branch before reviewing admissions.');
        }
    }

    private function assertApplicationBranch(User $actor, AdmissionApplication $application): void
    {
        if ($actor->isBranchManager()) abort_unless((int) $actor->branch_id === (int) $application->branch_id, 404);
    }
}
