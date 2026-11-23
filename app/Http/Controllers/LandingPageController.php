<?php

namespace App\Http\Controllers;

use App\Services\HomepageStatistics;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function __invoke(HomepageStatistics $statistics): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect('/dashboard');
        }

        return view('landing', [
            'homepageStatistics' => $statistics->get(),
        ]);
    }
}
