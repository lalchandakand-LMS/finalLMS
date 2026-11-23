<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Setting;
use Illuminate\View\View;

class PublicAchievementController extends Controller
{
    public function boardResults(): View
    {
        return view('board-results.index');
    }

    public function show(string $certificateNumber): View
    {
        $certificate = Certificate::query()
            ->where('certificate_number', $certificateNumber)
            ->where('status', 'issued')
            ->with(['user:id,name', 'course:id,title'])
            ->firstOrFail();

        $institutionName = Setting::value('institution_name', config('app.name'));

        return view('achievements.show', compact('certificate', 'institutionName'));
    }
}
