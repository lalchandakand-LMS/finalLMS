<?php

declare(strict_types=1);

namespace Aloms\AdmissionManagement;

use App\Http\Controllers\Controller;
use App\Models\PluginInstallation;
use App\Models\User;
use App\Services\Plugins\PluginSettingsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
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

    public function publicForm(): View
    {
        return view('admission-management::public.apply');
    }

    public function applyPublic(Request $request, PluginSettingsManager $settings): RedirectResponse
    {
        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.admission-management')->firstOrFail();
        abort_if(strtolower((string) ($settings->get($plugin, 'intake_status') ?? 'open')) !== 'open', 403, 'Admission applications are currently closed.');
        $data = $this->validatedApplicant($request);
        $data['email'] = strtolower(trim($data['email']));
        $data['application_source'] = 'website';
        $data['status'] = 'pending';
        $data['application_code'] = $this->newApplicationCode();
        $application = AdmissionApplication::query()->create($data);

        return redirect()->route('admission.public.track')->with('status', 'Application submitted. Keep your tracking ID: '.$application->application_code);
    }

    public function trackForm(): View
    {
        return view('admission-management::public.track', ['application' => null, 'searched' => false]);
    }

    public function track(Request $request): View
    {
        $data = $request->validate(['application_code' => ['required', 'string', 'max:40'], 'email' => ['required', 'email:rfc', 'max:160']]);
        $application = AdmissionApplication::query()
            ->where('application_code', strtoupper(trim($data['application_code'])))
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))])
            ->first(['application_code', 'applicant_name', 'desired_course', 'status', 'created_at', 'reviewed_at', 'interview_at', 'reviewer_notes']);

        return view('admission-management::public.track', ['application' => $application, 'searched' => true]);
    }

    public function index(Request $request): View
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $query = $this->scopedApplications($actor)
            ->when($request->filled('status') && in_array($request->input('status'), array_keys(self::TRANSITIONS), true), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = trim($request->string('q')->toString());
                $q->where(fn ($nested) => $nested->where('applicant_name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')->orWhere('application_code', 'like', '%'.$term.'%'));
            });

        return view('admission-management::admin.index', [
            'applications' => $query->with('documents')->latest()->paginate(20)->withQueryString(),
            'statusCounts' => $this->scopedApplications($actor)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'statuses' => array_keys(self::TRANSITIONS),
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
        $data['application_code'] = $this->newApplicationCode();
        $data['created_by'] = $actor->id;
        if ($actor->isBranchManager()) $data['branch_id'] = $actor->branch_id;
        $application = AdmissionApplication::query()->create($data);

        return back()->with('success', 'Application '.$application->application_code.' created.');
    }

    public function review(Request $request, int $application): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $data = $request->validate([
            'status' => ['required', 'in:pending,screening,shortlisted,approved,rejected,waitlisted'],
            'reviewer_notes' => [$request->input('status') === 'rejected' ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
            'interview_at' => ['nullable', 'date', 'after_or_equal:now'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($actor, $application, $data): void {
            $record = AdmissionApplication::query()->lockForUpdate()->findOrFail($application);
            $this->assertApplicationBranch($actor, $record);
            $next = $data['status'];
            abort_unless(in_array($next, self::TRANSITIONS[$record->status] ?? [], true), 422, 'Invalid admission status transition.');
            if (! empty($data['assigned_to'])) {
                $assignee = User::query()->findOrFail($data['assigned_to']);
                abort_unless(in_array($assignee->role, ['admin','branch_manager','teacher'], true), 422, 'Invalid reviewer role.');
                if ($actor->isBranchManager()) abort_unless((int) $assignee->branch_id === (int) $actor->branch_id, 403);
            }
            $record->forceFill([
                'status' => $next, 'reviewer_notes' => $data['reviewer_notes'] ?? $record->reviewer_notes,
                'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                'interview_at' => $data['interview_at'] ?? $record->interview_at,
                'assigned_to' => $data['assigned_to'] ?? $record->assigned_to,
            ])->save();
        }, 3);

        return back()->with('success', 'Admission application updated.');
    }

    public function storeDocument(Request $request, int $application): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $record = AdmissionApplication::query()->findOrFail($application);
        $this->assertApplicationBranch($actor, $record);
        $data = $request->validate(['document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);
        $file = $data['document'];
        $extension = strtolower((string) $file->extension());
        abort_unless(in_array($extension, ['pdf','jpg','jpeg','png'], true), 422);
        $path = $file->storeAs('admission-documents/'.$record->id, Str::uuid().'.'.$extension, 'local');
        if (! is_string($path) || $path === '') throw new \RuntimeException('Could not store admission document privately.');

        try {
            $record->documents()->create([
                'uploaded_by' => $actor->id, 'disk' => 'local', 'path' => $path,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                'mime_type' => (string) $file->getMimeType(), 'size_bytes' => (int) $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Private document uploaded.');
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
            'Content-Type' => $file->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function convertToStudent(Request $request, int $application): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $studentId = DB::transaction(function () use ($actor, $application): int {
            $record = AdmissionApplication::query()->lockForUpdate()->findOrFail($application);
            $this->assertApplicationBranch($actor, $record);
            abort_unless($record->status === 'approved', 422, 'Only approved applicants can be converted.');
            if ($record->converted_student_id) return (int) $record->converted_student_id;
            abort_if(User::query()->where('email', strtolower($record->email))->exists(), 409, 'An account already uses this email. Review it manually before conversion.');
            $student = User::query()->create([
                'name' => $record->applicant_name, 'email' => strtolower($record->email),
                'phone_number' => $record->phone, 'role' => 'student', 'branch_id' => $record->branch_id,
                'password' => Str::random(48),
            ]);
            $record->forceFill(['converted_student_id' => $student->id, 'converted_at' => now()])->save();

            return (int) $student->id;
        }, 3);
        $student = User::query()->findOrFail($studentId);
        $status = Password::sendResetLink(['email' => $student->email]);
        Log::info('Admission conversion password setup requested.', ['application_id' => $application, 'student_id' => $studentId, 'broker_status' => $status]);

        return back()->with('success', 'Student account created. A password setup email was requested if mail is configured.');
    }

    private function validatedApplicant(Request $request): array
    {
        return $request->validate([
            'applicant_name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email:rfc', 'max:160'],
            'phone' => ['required', 'string', 'max:40'], 'guardian_phone' => ['nullable', 'string', 'max:40'],
            'desired_course' => ['required', 'string', 'max:160'], 'preferred_batch' => ['nullable', 'string', 'max:80'],
            'previous_education' => ['nullable', 'string', 'max:160'], 'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:2000'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
    }

    private function newApplicationCode(): string
    {
        do { $code = 'ADM-'.strtoupper(Str::random(10)); }
        while (AdmissionApplication::query()->where('application_code', $code)->exists());
        return $code;
    }

    private function scopedApplications(User $actor): \Illuminate\Database\Eloquent\Builder
    {
        $query = AdmissionApplication::query();
        if ($actor->isBranchManager()) $query->where('branch_id', $actor->branch_id);
        return $query;
    }

    private function assertBranchAssigned(User $actor): void
    {
        if ($actor->isBranchManager()) abort_unless($actor->branch_id !== null, 403, 'Assign this account to a branch before reviewing admissions.');
    }

    private function assertApplicationBranch(User $actor, AdmissionApplication $application): void
    {
        if ($actor->isBranchManager()) abort_unless((int) $actor->branch_id === (int) $application->branch_id, 404);
    }
}
