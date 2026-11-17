<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    private const SUPPORTED = ['en', 'bn'];

    public function handle(Request $request, Closure $next): mixed
    {
        $defaultLocale = 'en';
        $timezone = 'UTC';

        if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            $defaultLocale = (string) \App\Models\Setting::value('institution_default_locale', 'en');
            $timezone = (string) \App\Models\Setting::value('institution_timezone', 'UTC');
        }

        if (! in_array($defaultLocale, self::SUPPORTED, true)) {
            $defaultLocale = 'en';
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'UTC';
        }

        $locale = $request->session()->get('locale', $defaultLocale);
        date_default_timezone_set($timezone);

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
