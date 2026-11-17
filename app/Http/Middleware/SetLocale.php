<?php

namespace App\\Http\\Middleware;

use Closure;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\App;
use Illuminate\\Support\\Facades\\Schema;

class SetLocale
{
    private const SUPPORTED = ['en', 'bn'];

    public function handle(Request $request, Closure $next): mixed
    {
        $defaultLocale = 'en';
        $timezone = 'UTC';

        if (Schema::hasTable('settings')) {
            $defaultLocale = (string) \\App\\Models\\Setting::value('institution_default_locale', 'en');
            $timezone = (string) \\App\\Models\\Setting::value('institution_timezone', 'UTC');
        }

        if (! in_array($defaultLocale, self::SUPPORTED, true)) {
            $defaultLocale = 'en';
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'UTC';
        }

        $locale = $request->session()->get('locale', $defaultLocale);
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = $defaultLocale;
        }

        // Restore process-global state after the response. This prevents one
        // institution's locale/timezone leaking into another request when the
        // application runs under a long-lived worker such as Octane.
        $previousLocale = App::getLocale();
        $previousTimezone = date_default_timezone_get();

        App::setLocale($locale);
        date_default_timezone_set($timezone);

        try {
            return $next($request);
        } finally {
            App::setLocale($previousLocale);
            date_default_timezone_set($previousTimezone);
        }
    }
}
