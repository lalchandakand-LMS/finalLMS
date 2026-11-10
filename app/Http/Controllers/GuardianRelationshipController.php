<?php

namespace App\Http\Controllers;

use App\Models\GuardianStudentLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GuardianRelationshipController extends Controller
{
    public function index(): View
    {
        $links = GuardianStudentLink::query()
            ->with(['guardian:id,name,email', 'student:id,name,email', 'verifier:id,name', 'revoker:id,name'])
            ->latest()
            ->paginate(25);

        $guardians = User::query()->where('role', 'guardian')->orderBy('name')->get(['id', 'name', 'email']);
        $students = User::query()->where('role', 'student')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.guardians.index', compact('links', 'guardians', 'students'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'guardian_id' => ['required', 'integer', 'different:student_id', 'exists:users,id'],
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'relationship' => ['required', 'in:father,mother,legal_guardian,other'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $guardian = User::query()->findOrFail($validated['guardian_id']);
        $student = User::query()->findOrFail($validated['student_id']);
        abort_unless($guardian->isGuardian() && $student->isStudent(), 422, 'Choose one guardian account and one student account.');

        DB::transaction(function () use ($validated): void {
            $link = GuardianStudentLink::query()->firstOrNew([
                'guardian_id' => $validated['guardian_id'],
                'student_id' => $validated['student_id'],
            ]);

            $link->fill([
                'relationship' => $validated['relationship'],
                'status' => 'approved',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'revoked_by' => null,
                'revoked_at' => null,
                'notes' => $validated['notes'] ?? null,
            ]);
            $link->created_by ??= auth()->id();
            $link->save();
        });

        return redirect()->route('admin.guardians.index')->with('success', 'Guardian relationship verified and activated.');
    }

    public function revoke(GuardianStudentLink $link): RedirectResponse
    {
        if ($link->status !== 'revoked') {
            $link->update([
                'status' => 'revoked',
                'revoked_by' => auth()->id(),
                'revoked_at' => now(),
            ]);
        }

        return redirect()->route('admin.guardians.index')->with('success', 'Guardian access has been revoked.');
    }
}
