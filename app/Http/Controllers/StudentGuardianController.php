<?php

namespace App\Http\Controllers;

use App\Models\GuardianStudentLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentGuardianController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);

        $links = GuardianStudentLink::query()
            ->where('student_user_id', $student->id)
            ->with('guardian:id,name,email')
            ->latest('requested_at')
            ->get();

        return view('student.guardian-requests', compact('links'));
    }

    public function respond(GuardianStudentLink $link, Request $request): RedirectResponse
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless($link->student_user_id === $student->id && $link->status === 'pending', 404);

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
        ]);

        if ($validated['decision'] === 'approve') {
            $link->update([
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $student->id,
                'verification_method' => 'student_consent',
                'rejected_at' => null,
                'revoked_at' => null,
            ]);

            return back()->with('success', __('Guardian access has been approved. The guardian can now view the permitted student information.'));
        }

        $link->update([
            'status' => 'rejected',
            'verified_at' => null,
            'verified_by' => null,
            'verification_method' => null,
            'rejected_at' => now(),
            'revoked_at' => null,
        ]);

        return back()->with('success', __('The guardian request was declined. No student information was shared.'));
    }

    public function revoke(GuardianStudentLink $link, Request $request): RedirectResponse
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless($link->student_user_id === $student->id && $link->status === 'verified', 404);

        $link->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        return back()->with('success', __('Guardian access has been revoked.'));
    }
}
