<?php

namespace App\\Http\\Controllers;

use App\\Models\\Batch;
use App\\Models\\Branch;
use App\\Models\\Enrollment;
use App\\Models\\User;
use Illuminate\\Http\\Request;
use Illuminate\\View\\View;

class BranchDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $manager = $request->user();
        $branchId = $manager->branch_id;

        abort_unless(
            $branchId && Branch::query()->whereKey($branchId)->where('is_active', true)->exists(),
            403,
            'No active branch is assigned to this account.'
        );

        $branch = Branch::query()->findOrFail($branchId);
        $batchQuery = Batch::query()->where('branch_id', $branchId);
        $batchIds = (clone $batchQuery)->pluck('id');

        $studentQuery = User::query()->where('role', 'student')->where(function ($query) use ($branchId, $batchIds): void {
            $query->where('branch_id', $branchId);
            if ($batchIds->isNotEmpty()) {
                $query->orWhereHas('enrollments', fn ($enrollments) => $enrollments
                    ->whereIn('status', ['active', 'completed'])
                    ->whereIn('batch_id', $batchIds));
            }
        });

        $stats = [
            'batches' => (clone $batchQuery)->count(),
            'open_batches' => (clone $batchQuery)->where('status', 'open')->count(),
            'students' => (clone $studentQuery)->distinct()->count('users.id'),
            'teachers' => User::query()->where('role', 'teacher')->where('branch_id', $branchId)->count(),
            'active_enrollments' => Enrollment::query()->whereIn('batch_id', $batchIds)->where('status', 'active')->count(),
        ];

        $recentBatches = (clone $batchQuery)->with(['course:id,title', 'teacher:id,name'])
            ->withCount(['enrollments' => fn ($query) => $query->whereIn('status', ['active', 'completed'])])
            ->latest('starts_at')->limit(8)->get();

        return view('branch.dashboard', compact('branch', 'stats', 'recentBatches'));
    }
}
