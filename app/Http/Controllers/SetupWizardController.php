<?php

namespace App\\Http\\Controllers;

use App\\Models\\Setting;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\View\\View;

class SetupWizardController extends Controller
{
    private const STEPS = [1, 2, 3];

    public function index(Request $request): View
    {
        $step = (int) $request->query('step', Setting::value('setup_wizard_step', 1));
        abort_unless(in_array($step, self::STEPS, true), 404);

        $settings = Setting::query()->pluck('value', 'key')->toArray();
        $paymentMethods = Setting::paymentMethods();

        return view('admin.setup-wizard.index', compact('step', 'settings', 'paymentMethods'));
    }

    public function update(Request $request): RedirectResponse
    {
        $step = (int) $request->input('step');
        abort_unless(in_array($step, self::STEPS, true), 404);

        if ($step === 1) {
            $validated = $request->validate([
                'institution_name' => ['required', 'string', 'max:160'],
                'institution_short_name' => ['nullable', 'string', 'max:60'],
                'institution_tagline' => ['nullable', 'string', 'max:180'],
                'institution_contact_email' => ['nullable', 'email', 'max:190'],
                'institution_contact_phone' => ['nullable', 'string', 'max:40'],
                'institution_website' => ['nullable', 'url', 'max:255'],
                'institution_address' => ['nullable', 'string', 'max:500'],
                'institution_default_locale' => ['required', 'in:en,bn'],
                'institution_timezone' => ['required', 'timezone'],
                'site_primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ]);

            $this->save($validated);
        } elseif ($step === 2) {
            $validated = $request->validate([
                'academic_year_name' => ['required', 'string', 'max:80'],
                'academic_year_start' => ['required', 'date'],
                'academic_year_end' => ['required', 'date', 'after_or_equal:academic_year_start'],
            ]);

            $this->save($validated);
        } else {
            $validated = $request->validate([
                'payment_methods' => ['required', 'array'],
                'payment_methods.bkash.enabled' => ['required', 'boolean'],
                'payment_methods.bkash.account' => ['nullable', 'string', 'max:120'],
                'payment_methods.bkash.instructions' => ['nullable', 'string', 'max:500'],
                'payment_methods.rocket.enabled' => ['required', 'boolean'],
                'payment_methods.rocket.account' => ['nullable', 'string', 'max:120'],
                'payment_methods.rocket.instructions' => ['nullable', 'string', 'max:500'],
                'payment_methods.bank.enabled' => ['required', 'boolean'],
                'payment_methods.bank.account' => ['nullable', 'string', 'max:120'],
                'payment_methods.bank.instructions' => ['nullable', 'string', 'max:500'],
                'payment_methods.manual.enabled' => ['required', 'boolean'],
                'payment_methods.manual.account' => ['nullable', 'string', 'max:120'],
                'payment_methods.manual.instructions' => ['nullable', 'string', 'max:500'],
            ]);

            $defaults = Setting::paymentMethods();
            foreach ($validated['payment_methods'] as $method => $values) {
                $defaults[$method] = array_merge($defaults[$method], $values);
                $defaults[$method]['label'] = $defaults[$method]['label'] ?: ucfirst($method);
            }
            Setting::setValue('payment_methods', json_encode($defaults, JSON_THROW_ON_ERROR));
            Setting::setValue('setup_wizard_completed_at', now()->toIso8601String());
            Setting::setValue('setup_wizard_step', 3);

            return redirect()->route('admin.setup-wizard.index')->with('success', 'Initial setup is complete. You can revisit these settings at any time.');
        }

        Setting::setValue('setup_wizard_step', min(3, $step + 1));

        return redirect()->route('admin.setup-wizard.index', ['step' => min(3, $step + 1)])
            ->with('success', 'Your progress has been saved. You can continue later.');
    }

    public function skip(Request $request): RedirectResponse
    {
        $step = (int) $request->input('step');
        abort_unless(in_array($step, self::STEPS, true), 404);

        if ($step === 3) {
            Setting::setValue('setup_wizard_completed_at', now()->toIso8601String());
            Setting::setValue('setup_wizard_step', 3);

            return redirect()->route('admin.setup-wizard.index')->with('success', 'Setup wizard marked complete. Optional integrations can be configured later.');
        }

        $next = $step + 1;
        Setting::setValue('setup_wizard_step', $next);

        return redirect()->route('admin.setup-wizard.index', ['step' => $next])
            ->with('status', 'This optional step was skipped. You can return to it later.');
    }

    private function save(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::setValue($key, $value ?? '');
        }
    }
}
