<?php

namespace App\Http\Controllers;

use App\Models\GuardianStudent;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GuardianController extends Controller
{
    public function index(): View
    {
        $links = GuardianStudent::query()
            ->where('guardian_id', Auth::id())
            ->with([
                'student.enrollments.course',
                'student.invoices.payments',
                'student.attendances.batch',
                'student.certificates.course',
            ])
            ->orderBy('created_at')
            ->get();

        return view('guardian.dashboard', compact('links'));
    }

    public function show(GuardianStudent $guardianStudent): View
    {
        abort_unless($guardianStudent->guardian_id === Auth::id(), 404);

        $guardianStudent->load([
            'student.enrollments.course',
            'student.invoices.payments',
            'student.attendances.batch',
            'student.certificates.course',
        ]);

        return view('guardian.child', ['student' => $guardianStudent->student]);
    }
}
