<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;

class SetLocale
{
    private const SUPPORTED = ['en', 'bn'];

    public function handle(Request $request, Closure $next): mixed
    {
        $defaultLocale = 'en';
        $timezone = 'Asia/Dhaka';

        try {
            if (Schema::hasTable('settings')) {
                $defaultLocale = (string) \App\Models\Setting::value('institution_default_locale', 'en');
                $timezone = (string) \App\Models\Setting::value('institution_timezone', 'Asia/Dhaka');
            }
        } catch (\Throwable) {
            // The installer may be running before the database is configured
            // or migrations have created the settings table. Keep safe defaults.
        }

        if (! in_array($defaultLocale, self::SUPPORTED, true)) {
            $defaultLocale = 'en';
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'Asia/Dhaka';
        }

        $locale = $request->session()->get('locale', $defaultLocale);
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = $defaultLocale;
        }

        // Keep the installation timezone as metadata for date presenters and
        // notification formatters. Do not mutate PHP's process timezone here:
        // request payload dates are parsed by Laravel using the process timezone,
        // and changing it mid-request would reinterpret UTC timestamps from forms.
        config(['app.institution_timezone' => $timezone]);

        $previousLocale = App::getLocale();
        App::setLocale($locale);

        try {
            return $next($request);
        } finally {
            App::setLocale($previousLocale);
        }
    }
}
