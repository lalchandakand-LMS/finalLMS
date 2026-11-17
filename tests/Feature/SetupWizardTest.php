<?php

namespace Tests\\Feature;

use App\\Models\\Setting;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_profile_and_resume_on_academic_year_step(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.setup-wizard.update'), [
            'step' => 1,
            'institution_name' => 'Alom Learning Center',
            'institution_default_locale' => 'bn',
            'institution_timezone' => 'Asia/Dhaka',
            'site_primary_color' => '#123456',
        ])->assertRedirect(route('admin.setup-wizard.index', ['step' => 2]));

        $this->assertDatabaseHas('settings', ['key' => 'institution_name', 'value' => 'Alom Learning Center']);
        $this->assertDatabaseHas('settings', ['key' => 'setup_wizard_step', 'value' => '2']);

        $this->actingAs($admin)->get(route('admin.setup-wizard.index'))
            ->assertOk()->assertSee('Academic year');
    }

    public function test_admin_can_save_academic_year_and_complete_payment_step(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.setup-wizard.update'), [
            'step' => 2,
            'academic_year_name' => '2026–2027',
            'academic_year_start' => '2026-07-01',
            'academic_year_end' => '2027-06-30',
        ])->assertRedirect(route('admin.setup-wizard.index', ['step' => 3]));

        $this->actingAs($admin)->post(route('admin.setup-wizard.update'), [
            'step' => 3,
            'payment_methods' => [
                'bkash' => ['enabled' => '1', 'account' => '01700000000', 'instructions' => 'Send money'],
                'rocket' => ['enabled' => '0', 'account' => '', 'instructions' => ''],
                'bank' => ['enabled' => '0', 'account' => '', 'instructions' => ''],
                'manual' => ['enabled' => '1', 'account' => 'Office', 'instructions' => 'Pay at office'],
            ],
        ])->assertRedirect(route('admin.setup-wizard.index'));

        $this->assertNotEmpty(Setting::value('setup_wizard_completed_at'));
        $this->assertSame('01700000000', Setting::paymentMethods()['bkash']['account']);
        $this->assertTrue((bool) Setting::paymentMethods()['bkash']['enabled']);
    }

    public function test_invalid_academic_year_dates_are_rejected_and_progress_is_not_advanced(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('admin.setup-wizard.index', ['step' => 2]))
            ->post(route('admin.setup-wizard.update'), [
                'step' => 2,
                'academic_year_name' => '2026',
                'academic_year_start' => '2027-01-01',
                'academic_year_end' => '2026-01-01',
            ])->assertRedirect(route('admin.setup-wizard.index', ['step' => 2]))
            ->assertSessionHasErrors('academic_year_end');

        $this->assertSame('1', Setting::value('setup_wizard_step', '1'));
    }

    public function test_non_admin_cannot_access_setup_wizard(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.setup-wizard.index'))->assertForbidden();
    }
}
