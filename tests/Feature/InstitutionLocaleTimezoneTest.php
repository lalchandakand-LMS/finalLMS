<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

class InstitutionLocaleTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_institution_defaults_are_applied_only_for_the_current_request(): void
    {
        Setting::setValue('institution_default_locale', 'bn');
        Setting::setValue('institution_timezone', 'Asia/Dhaka');

        $previousLocale = app()->getLocale();
        $previousTimezone = date_default_timezone_get();

        $request = Request::create('/');
        $session = new Store('test-session', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $response = (new SetLocale())->handle($request, function () {
            $this->assertSame('bn', app()->getLocale());
            $this->assertSame('Asia/Dhaka', date_default_timezone_get());

            return response('ok');
        });

        $this->assertSame('ok', $response->getContent());
        $this->assertSame($previousLocale, app()->getLocale());
        $this->assertSame($previousTimezone, date_default_timezone_get());
    }

    public function test_explicit_session_locale_overrides_institution_default_and_is_restored(): void
    {
        Setting::setValue('institution_default_locale', 'bn');
        Setting::setValue('institution_timezone', 'Asia/Dhaka');

        $previousLocale = app()->getLocale();
        $previousTimezone = date_default_timezone_get();

        $request = Request::create('/');
        $session = new Store('test-session', new ArraySessionHandler(120));
        $session->start();
        $session->put('locale', 'en');
        $request->setLaravelSession($session);

        (new SetLocale())->handle($request, function () {
            $this->assertSame('en', app()->getLocale());
            $this->assertSame('Asia/Dhaka', date_default_timezone_get());

            return response('ok');
        });

        $this->assertSame($previousLocale, app()->getLocale());
        $this->assertSame($previousTimezone, date_default_timezone_get());
    }

    public function test_unsupported_saved_defaults_fall_back_safely(): void
    {
        Setting::setValue('institution_default_locale', 'xx');
        Setting::setValue('institution_timezone', 'Mars/Phobos');

        $request = Request::create('/');
        $session = new Store('test-session', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        (new SetLocale())->handle($request, function () {
            $this->assertSame('en', app()->getLocale());
            $this->assertSame('UTC', date_default_timezone_get());

            return response('ok');
        });
    }
}
