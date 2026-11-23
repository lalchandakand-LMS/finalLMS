<?php

namespace App\\Http\\Controllers;

use App\\Services\\HomepageStatistics;
use Illuminate\\View\\View;

class LandingPageController extends Controller
{
    public function __invoke(HomepageStatistics $statistics): View
    {
        return view('landing', [
            'homepageStatistics' => $statistics->get(),
        ]);
    }
}
