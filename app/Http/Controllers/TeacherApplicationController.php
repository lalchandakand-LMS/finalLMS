<?php

namespace App\Http\Controllers;

use App\Models\TeacherApplication;\nuse Illuminate\Auth\Events\Registered;
use App\Models\User;
use App\Services\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class TeacherApplicationController extends Controller
{
    public function create(): View { return view('auth.teacher-register'); }

    public function store(Request $request, SmsGateway $sms): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:160'],
            'email' => ['required','email','max:255','unique:users,email','unique:teacher_applications,email'],
            'phone_number' => ['required','regex:/^\\+8801[3-9][0-9]{8}$/','unique:users,phone_number','unique:teacher_applications,phone_number'],
            'institution_name' => ['required','string','max:200'],
            'designation' => ['required','string','max:120'],
            'nid_number' => ['required','string','regex:/^(?:[0-9]{10}|[0-9]{13}|[0-9]{17})$/'],
            'nid_document' => ['required','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'institution_document' => ['required','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'password' => ['required','confirmed',Password::defaults()],
            'consent' => ['accepted'],
        ]);

        abort_unless($sms->isConfigured(), 503, 'Teacher mobile verification is temporarily unavailable. Please contact the academy.');

        $otp = (string) random_int(100000, 999999);
        $nidPath = $request->file('nid_document')->store('teacher-kyc/nid', 'local');
        $institutionPath = $request->file('institution_document')->store('teacher-kyc/institution', 'local');

        try {
            $application = TeacherApplication::create([
                'name' => $data['name'], 'email' => strtolower($data['email']),
                'phone_number' => $data['phone_number'], 'institution_name' => $data['institution_name'],
                'designation' => $data['designation'], 'nid_encrypted' => Crypt::encryptString($data['nid_number']),
                'password' => Hash::make($data['password']), 'nid_document_path' => $nidPath,
                'institution_document_path' => $institutionPath, 'otp_hash' => Hash::make($otp),
                'otp_expires_at' => now()->addMinutes(10), 'status' => 'pending_phone_verification',
            ]);
            $sms->send($application->phone_number, "Alom's English Center teacher application verification code: {$otp}. Expires in 10 minutes.");
        } catch (Throwable $exception) {
            if (isset($application)) $application->delete();
            Storage::disk('local')->delete([$nidPath, $institutionPath]);
            return back()->withErrors(['phone_number' => 'Verification SMS could not be sent. Please try again later.'])->withInput($request->except(['password','password_confirmation','nid_number']));
        }

        $request->session()->put('teacher_application_id', $application->id);
        return redirect()->route('teacher.apply.verify')->with('success', 'Application received. Verify your mobile number to submit it for review.');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $application = TeacherApplication::find($request->session()->get('teacher_application_id'));
        abort_unless($application && $application->status === 'pending_phone_verification', 404);
        return view('auth.teacher-verify', compact('application'));
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required','digits:6']]);
        $application = TeacherApplication::find($request->session()->get('teacher_application_id'));
        abort_unless($application && $application->status === 'pending_phone_verification' && $application->otp_expires_at?->isFuture(), 410);
        $key = 'teacher-application-otp:'.$application->id;
        if (RateLimiter::tooManyAttempts($key, 5)) return back()->withErrors(['code' => 'Too many attempts. Please restart the application process.']);
        if (! Hash::check($data['code'], (string) $application->otp_hash)) {
            RateLimiter::hit($key, 600);
            return back()->withErrors(['code' => 'Incorrect verification code.']);
        }
        $application->update(['status' => 'pending_admin_review','phone_verified_at' => now(),'otp_hash' => null,'otp_expires_at' => null]);
        RateLimiter::clear($key);
        $request->session()->forget('teacher_application_id');
        return redirect()->route('login')->with('success', 'Mobile verified. Your teacher application is awaiting administrator document review.');
    }

    public function adminIndex(): View
    {
        $applications = TeacherApplication::with('reviewer')->latest()->paginate(20);
        return view('admin.teacher-applications.index', compact('applications'));
    }

    public function document(TeacherApplication $application, string $type)
    {
        abort_unless(in_array($type, ['nid','institution'], true), 404);
        $path = $type === 'nid' ? $application->nid_document_path : $application->institution_document_path;
        abort_unless(Storage::disk('local')->exists($path), 404);
        $filename = $type === 'nid' ? 'nid-document' : 'institution-proof';
        return Storage::disk('local')->download($path, $filename, ['Cache-Control' => 'private, no-store']);
    }

    public function approve(TeacherApplication $application, Request $request): RedirectResponse
    {
        abort_unless($application->status === 'pending_admin_review' && $application->phone_verified_at, 422);
        $user = User::create([
            'name' => $application->name, 'email' => $application->email,
            'phone_number' => $application->phone_number, 'password' => $application->password,
            'role' => 'teacher',
        ]);
        $application->update(['status' => 'approved','reviewed_by' => $request->user()->id,'reviewed_at' => now(),'user_id' => $user->id]);\n        event(new Registered($user));
        return back()->with('success', 'Teacher approved. A teacher account has been created; email verification is required before dashboard access.');
    }

    public function reject(TeacherApplication $application, Request $request): RedirectResponse
    {
        abort_unless(in_array($application->status, ['pending_admin_review','pending_phone_verification'], true), 422);
        $data = $request->validate(['review_note' => ['required','string','max:1000']]);
        $application->update(['status' => 'rejected','reviewed_by' => $request->user()->id,'reviewed_at' => now(),'review_note' => $data['review_note'],'otp_hash' => null,'otp_expires_at' => null]);
        return back()->with('success', 'Teacher application rejected.');
    }
}