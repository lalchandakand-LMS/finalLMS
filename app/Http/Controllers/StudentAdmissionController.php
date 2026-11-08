<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\ActivityLog;
use App\Models\StudentAdmissionProfile;
use App\Models\StudentCourseAttendance;
use App\Models\StudentCourseSession;
use App\Models\StudentDocument;
use App\Models\StudentGuardian;
use App\Models\StudentReferral;
use App\Models\StudentDevice;
use App\Models\DeviceSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StudentAdmissionController extends Controller
{
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required','string','max:180'],
            'email' => ['required','email','max:255','unique:users,email'],
            'phone_number' => ['required','string','regex:/^\+[1-9][0-9]{7,14}$/','unique:users,phone_number'],
            'password' => ['required','confirmed',\Illuminate\Validation\Rules\Password::defaults()],
            'source_channel' => ['required', Rule::in(['google','facebook','youtube','friend','referral','website','banner','other'])],
            'source_details' => ['nullable','string','max:255'],
            'school_name' => ['required','string','max:180'],
            'class_name' => ['required','string','max:80'],
            'school_roll' => ['nullable','string','max:80'],
            'course_id' => ['required','integer',Rule::exists('courses','id')->where('status','published')],
            'course_updates_opt_in' => ['nullable','boolean'],
            'current_address' => ['required','string','max:2000'],
            'current_post_office' => ['required','string','max:120'],
            'current_district' => ['required','string','max:120'],
            'current_division' => ['required','string','max:120'],
            'current_country' => ['required','string','max:120'],
            'permanent_address' => ['required','string','max:2000'],
            'permanent_post_office' => ['required','string','max:120'],
            'permanent_district' => ['required','string','max:120'],
            'permanent_division' => ['required','string','max:120'],
            'permanent_country' => ['required','string','max:120'],
            'living_type' => ['required',Rule::in(['residential','non_residential'])],
            'special_quota' => ['required',Rule::in(['none','disability','freedom_fighter','ethnic_minority','other'])],
            'special_quota_notes' => ['nullable','string','max:1500'],
            'profile_photo' => ['required','image','mimes:jpg,jpeg,png','max:5120'],
            'quota_document' => ['required_if:special_quota,disability,other','nullable','file','mimes:jpg,jpeg,png,pdf','max:10240'],
            'father.name' => ['nullable','string','max:180'],
            'father.mobile' => ['nullable','string','regex:/^\+[1-9][0-9]{7,14}$/'],
            'father.email' => ['nullable','email','max:255'],
            'father.occupation' => ['nullable','string','max:120'],
            'father.nid_number' => ['nullable','string','max:40'],
            'father.nid_front' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'father.nid_back' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'mother.name' => ['nullable','string','max:180'],
            'mother.mobile' => ['nullable','string','regex:/^\+[1-9][0-9]{7,14}$/'],
            'mother.email' => ['nullable','email','max:255'],
            'mother.occupation' => ['nullable','string','max:120'],
            'mother.nid_number' => ['nullable','string','max:40'],
            'mother.nid_front' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'mother.nid_back' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'guardian.name' => ['nullable','string','max:180'],
            'guardian.mobile' => ['nullable','string','regex:/^\+[1-9][0-9]{7,14}$/'],
            'guardian.email' => ['nullable','email','max:255'],
            'guardian.relationship' => ['nullable','string','max:60'],
            'guardian.occupation' => ['nullable','string','max:120'],
            'guardian.nid_number' => ['nullable','string','max:40'],
            'guardian.nid_front' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'guardian.nid_back' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'referral_code' => ['nullable','string','max:24',Rule::exists('student_admission_profiles','referral_code')],
            'privacy_consent' => ['accepted'],
        ]);

        $hasCompleteNid = false;
        foreach (['father','mother','guardian'] as $parentType) {
            if ($request->hasFile($parentType.'.nid_front') && $request->hasFile($parentType.'.nid_back')) {
                $hasCompleteNid = true;
                break;
            }
        }
        if (! $hasCompleteNid) {
            return back()->withInput()->withErrors(['father.nid_front' => __('Attach both front and back sides of at least one parent or legal guardian NID.')]);
        }

        if (empty($validated['father']['mobile'] ?? null) && empty($validated['mother']['mobile'] ?? null) && empty($validated['guardian']['mobile'] ?? null)) {
            return back()->withInput()->withErrors(['guardian.mobile' => __('At least one parent or guardian mobile number is required.')]);
        }

        $user = DB::transaction(function () use ($request, $validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'phone_number' => $validated['phone_number'],
                'password' => $validated['password'],
                'role' => 'student',
                'account_status' => 'active',
            ]);

            $profile = StudentAdmissionProfile::create([
                'user_id' => $user->id,
                'admission_number' => 'AEC-'.now()->format('Y').'-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                'referral_code' => 'AEC'.Str::upper(Str::random(8)),
                'source_channel' => $validated['source_channel'],
                'source_details' => $validated['source_details'] ?? null,
                'school_name' => $validated['school_name'],
                'class_name' => $validated['class_name'],
                'school_roll' => $validated['school_roll'] ?? null,
                'interested_course_id' => $validated['course_id'],
                'quoted_course_price' => Course::findOrFail($validated['course_id'])->price,
                'course_updates_opt_in' => (bool) ($validated['course_updates_opt_in'] ?? false),
                'privacy_consent_at' => now(),
                'privacy_consent_version' => '2026-11-09-v1',
                'current_address' => $validated['current_address'],
                'current_post_office' => $validated['current_post_office'],
                'current_district' => $validated['current_district'],
                'current_division' => $validated['current_division'],
                'current_country' => $validated['current_country'],
                'permanent_address' => $validated['permanent_address'],
                'permanent_post_office' => $validated['permanent_post_office'],
                'permanent_district' => $validated['permanent_district'],
                'permanent_division' => $validated['permanent_division'],
                'permanent_country' => $validated['permanent_country'],
                'living_type' => $validated['living_type'],
                'special_quota' => $validated['special_quota'],
                'special_quota_notes' => $validated['special_quota_notes'] ?? null,
                'admission_status' => 'pending',
            ]);

            $guardians = [];
            foreach (['father' => 'father', 'mother' => 'mother', 'guardian' => 'guardian'] as $key => $type) {
                $data = $validated[$key] ?? [];
                if (empty($data['name']) && empty($data['mobile'])) {
                    continue;
                }
                $guardians[$key] = StudentGuardian::create([
                    'student_id' => $user->id,
                    'guardian_type' => $type,
                    'relationship' => $data['relationship'] ?? ucfirst($type),
                    'name' => $data['name'] ?? ucfirst($type),
                    'mobile_number' => $data['mobile'] ?? null,
                    'email' => $data['email'] ?? null,
                    'occupation' => $data['occupation'] ?? null,
                    'nid_number' => $data['nid_number'] ?? null,
                    'is_primary' => empty($guardians),
                    'contact_consent' => true,
                ]);
            }

            $this->storeEncryptedDocument($user, null, 'profile_photo', $request->file('profile_photo'));
            foreach (['father','mother','guardian'] as $type) {
                $guardian = $guardians[$type] ?? null;
                if (! $guardian) continue;
                foreach (['nid_front','nid_back'] as $side) {
                    if ($request->hasFile($type.'.'.$side)) {
                        $this->storeEncryptedDocument($user, $guardian, $type.'_'.$side, $request->file($type.'.'.$side));
                    }
                }
            }
            if ($request->hasFile('quota_document')) {
                $this->storeEncryptedDocument($user, null, 'quota_evidence', $request->file('quota_document'));
            }

            if (! empty($validated['referral_code'])) {
                $referrer = StudentAdmissionProfile::where('referral_code', $validated['referral_code'])->firstOrFail();
                abort_if($referrer->user_id === $user->id, 422, __('You cannot refer yourself.'));
                StudentReferral::create([
                    'referrer_student_id' => $referrer->user_id,
                    'referred_student_id' => $user->id,
                    'referral_code' => $referrer->referral_code,
                    'status' => 'registered',
                ]);
            }

            return $user;
        });

        event(new \Illuminate\Auth\Events\Registered($user));

        return redirect()->route('login')->with('success', __('Admission application submitted. Sign in to verify your mobile number; the academy will review your admission details.'));
    }

    public function edit(): View
    {
        $user = request()->user();
        abort_unless($user->isStudent(), 403);
        $profile = $user->admissionProfile()->with(['guardians.documents','documents','interestedCourse'])->firstOrFail();
        $courses = Course::where('status','published')->orderBy('title')->get();
        return view('profile.admission-edit', compact('user','profile','courses'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isStudent(), 403);
        $profile = $user->admissionProfile()->firstOrFail();
        $validated = $request->validate([
            'school_name' => ['required','string','max:180'],
            'class_name' => ['required','string','max:80'],
            'school_roll' => ['nullable','string','max:80'],
            'course_id' => ['required','integer',Rule::exists('courses','id')->where('status','published')],
            'course_updates_opt_in' => ['nullable','boolean'],
            'current_address' => ['required','string','max:2000'],
            'current_post_office' => ['required','string','max:120'],
            'current_district' => ['required','string','max:120'],
            'current_division' => ['required','string','max:120'],
            'current_country' => ['required','string','max:120'],
            'permanent_address' => ['required','string','max:2000'],
            'permanent_post_office' => ['required','string','max:120'],
            'permanent_district' => ['required','string','max:120'],
            'permanent_division' => ['required','string','max:120'],
            'permanent_country' => ['required','string','max:120'],
            'living_type' => ['required',Rule::in(['residential','non_residential'])],
            'special_quota' => ['required',Rule::in(['none','disability','freedom_fighter','ethnic_minority','other'])],
            'special_quota_notes' => ['nullable','string','max:1500'],
            'profile_photo' => ['nullable','image','mimes:jpg,jpeg,png','max:5120'],
            'quota_document' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:10240'],
            'guardian_name' => ['required','string','max:180'],
            'guardian_relationship' => ['required','string','max:60'],
            'guardian_mobile' => ['required','string','regex:/^\+[1-9][0-9]{7,14}$/'],
            'guardian_email' => ['nullable','email','max:255'],
            'guardian_occupation' => ['nullable','string','max:120'],
            'guardian_nid_number' => ['nullable','string','max:40'],
            'guardian_nid_front' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'guardian_nid_back' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:5120'],
        ]);
        $course = Course::findOrFail($validated['course_id']);
        $guardianData = [
            'relationship' => $validated['guardian_relationship'],
            'name' => $validated['guardian_name'],
            'mobile_number' => $validated['guardian_mobile'],
            'email' => $validated['guardian_email'] ?? null,
            'occupation' => $validated['guardian_occupation'] ?? null,
            'contact_consent' => true,
        ];
        if ($request->filled('guardian_nid_number')) {
            $guardianData['nid_number'] = $validated['guardian_nid_number'];
        }
        $guardianRecord = StudentGuardian::updateOrCreate(
            ['student_id' => $user->id, 'guardian_type' => 'guardian'],
            $guardianData + ['is_primary' => ! StudentGuardian::where('student_id', $user->id)->where('is_primary', true)->exists()]
        );
        $profile->update([
            ...collect($validated)->except(['course_id','profile_photo','quota_document','guardian_name','guardian_relationship','guardian_mobile','guardian_email','guardian_occupation','guardian_nid_number','guardian_nid_front','guardian_nid_back'])->all(),
            'interested_course_id' => $course->id,
            'quoted_course_price' => $course->price,
        ]);
        if ($request->hasFile('profile_photo')) $this->storeEncryptedDocument($user, null, 'profile_photo', $request->file('profile_photo'));
        if ($request->hasFile('quota_document')) $this->storeEncryptedDocument($user, null, 'quota_evidence', $request->file('quota_document'));
        foreach (['nid_front','nid_back'] as $side) {
            if ($request->hasFile('guardian_'.$side)) $this->storeEncryptedDocument($user, $guardianRecord, 'guardian_'.$side, $request->file('guardian_'.$side));
        }
        return back()->with('success', __('Admission profile and guardian contacts updated. Sensitive documents are stored privately.'));
    }

    public function document(StudentDocument $document): Response
    {
        $user = request()->user();
        $allowed = $user->isAdmin()
            || $document->student_id === $user->id
            || $document->student()->whereHas('studentGuardians', fn ($q) => $q->where('guardian_user_id', $user->id))->exists();
        abort_unless($allowed, 403);
        $path = $document->encrypted_path;
        abort_unless(Storage::disk('local')->exists($path), 404);
        try {
            $bytes = Crypt::decryptString(Storage::disk('local')->get($path));
        } catch (Throwable) {
            abort(404);
        }
        $inline = $document->document_type === 'profile_photo' && str_starts_with($document->mime_type, 'image/');
        return response($bytes, 200, [
            'Content-Type' => $document->mime_type,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.str_replace('"','',basename($document->original_name)).'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
        ]);
    }

    public function adminIndex(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable','string','max:120'],
            'status' => ['nullable',Rule::in(['active','inactive','deleted'])],
            'admission_status' => ['nullable',Rule::in(['pending','approved','rejected'])],
        ]);
        $students = User::where('role','student')->with(['admissionProfile.interestedCourse','studentGuardians'])
            ->when($validated['search'] ?? null, function ($q,$term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('name','like','%'.$term.'%')->orWhere('email','like','%'.$term.'%')->orWhere('phone_number','like','%'.$term.'%')
                        ->orWhereHas('admissionProfile', fn ($p) => $p->where('admission_number','like','%'.$term.'%')->orWhere('school_name','like','%'.$term.'%'));
                });
            })
            ->when($validated['status'] ?? null, fn ($q,$s) => $q->where('account_status',$s))
            ->when($validated['admission_status'] ?? null, fn ($q,$s) => $q->whereHas('admissionProfile',fn($p)=>$p->where('admission_status',$s)))
            ->latest()->paginate(30)->withQueryString();
        $counts = [
            'total' => User::where('role','student')->count(),
            'active' => User::where('role','student')->where('account_status','active')->count(),
            'inactive' => User::where('role','student')->where('account_status','inactive')->count(),
            'pending' => StudentAdmissionProfile::where('admission_status','pending')->count(),
        ];
        return view('admin.admissions.index', compact('students','counts'));
    }

    public function adminShow(User $student): View
    {
        abort_unless($student->isStudent(), 404);
        $student->load(['admissionProfile.interestedCourse','admissionProfile.reviewer','studentGuardians.documents','admissionProfile.documents','referralsMade.referred','referralsReceived.referrer']);
        $sessions = StudentCourseAttendance::with('session.course')->where('student_id',$student->id)->latest()->take(30)->get();
        $activityLogs = ActivityLog::where('user_id',$student->id)->latest()->take(30)->get();
        return view('admin.admissions.show', compact('student','sessions','activityLogs'));
    }

    public function approve(User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $student->admissionProfile()->update([
            'admission_status' => 'approved', 'reviewed_by' => request()->user()->id, 'reviewed_at' => now(),
        ]);
        $student->update(['account_status' => 'active']);
        return back()->with('success', __('Admission approved and student account activated.'));
    }

    public function reject(User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $student->admissionProfile()->update([
            'admission_status' => 'rejected',
            'reviewed_by' => request()->user()->id,
            'reviewed_at' => now(),
        ]);
        DeviceSession::where('user_id',$student->id)->whereNull('revoked_at')->update([
            'revoked_at'=>now(),'revoke_reason'=>'Admission application rejected','updated_at'=>now(),
        ]);
        StudentDevice::where('user_id',$student->id)->where('status','approved')->update(['status'=>'revoked','updated_at'=>now()]);
        $student->update(['account_status'=>'inactive']);
        return back()->with('success', __('Admission rejected and student account deactivated.'));
    }

    public function setStatus(User $student, Request $request): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $data = $request->validate(['account_status' => ['required',Rule::in(['active','inactive'])]]);
        if ($data['account_status'] === 'inactive') {
            DeviceSession::where('user_id',$student->id)->whereNull('revoked_at')->update(['revoked_at'=>now(),'revoke_reason'=>'Student account deactivated','updated_at'=>now()]);
            StudentDevice::where('user_id',$student->id)->where('status','approved')->update(['status'=>'revoked','updated_at'=>now()]);
        }
        $student->update($data);
        return back()->with('success', __('Student account status updated.'));
    }

    public function requestDeletion(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isStudent(), 403);
        $request->validate(['confirm' => ['accepted']]);
        $request->user()->forceFill(['deletion_requested_at' => now()])->save();
        return redirect()->route('profile.edit')->with('success', __('Profile deletion request sent to the academy. Your account remains available until the request is reviewed.'));
    }

    public function anonymize(User $student): RedirectResponse
    {
        abort_unless($student->isStudent() && $student->deletion_requested_at, 404);
        DB::transaction(function () use ($student) {
            foreach ($student->admissionProfile?->documents ?? collect() as $doc) Storage::disk('local')->delete($doc->encrypted_path);
            foreach ($student->studentGuardians as $guardian) {
                foreach ($guardian->documents as $doc) Storage::disk('local')->delete($doc->encrypted_path);
                $guardian->delete();
            }
            StudentDocument::where('student_id',$student->id)->delete();
            StudentReferral::where('referrer_student_id',$student->id)->orWhere('referred_student_id',$student->id)->delete();
            $student->admissionProfile?->delete();
            DeviceSession::where('user_id',$student->id)->whereNull('revoked_at')->update(['revoked_at'=>now(),'revoke_reason'=>'Student profile deletion','updated_at'=>now()]);
            StudentDevice::where('user_id',$student->id)->delete();
            $student->update([
                'name' => 'Deleted Student '.$student->id,
                'email' => 'deleted-student-'.$student->id.'@invalid.local',
                'phone_number' => null,
                'account_status' => 'deleted',
                'deletion_requested_at' => null,
            ]);
        });
        return redirect()->route('admin.admissions.index')->with('success', __('Personal admission data and private documents were deleted. Financial and academic records remain anonymized for audit integrity.'));
    }

    private function storeEncryptedDocument(User $student, ?StudentGuardian $guardian, string $type, $file): StudentDocument
    {
        $oldDocuments = StudentDocument::query()
            ->where('student_id', $student->id)
            ->where('document_type', $type)
            ->when($guardian, fn ($query) => $query->where('guardian_id', $guardian->id), fn ($query) => $query->whereNull('guardian_id'))
            ->get();
        foreach ($oldDocuments as $oldDocument) {
            Storage::disk('local')->delete($oldDocument->encrypted_path);
            $oldDocument->delete();
        }

        $document = StudentDocument::create([
            'student_id' => $student->id,
            'guardian_id' => $guardian?->id,
            'document_type' => $type,
            'original_name' => basename($file->getClientOriginalName()),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
            'encrypted_path' => 'student-documents/'.$student->id.'/'.Str::uuid().'.enc',
            'content_hash' => hash_file('sha256', $file->getRealPath()),
        ]);
        Storage::disk('local')->put($document->encrypted_path, Crypt::encryptString(file_get_contents($file->getRealPath())));
        return $document;
    }
}
