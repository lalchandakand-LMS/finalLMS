<?php

namespace Tests\\Feature;

use Tests\\TestCase;

class InstallerTest extends TestCase
{
    public function test_installation_page_is_available_before_setup(): void
    {
        $this->get(route('install.index'))->assertOk()->assertSee('Install LMS securely');
    }

    public function test_installer_requires_valid_site_database_and_admin_details(): void
    {
        $this->from(route('install.index'))
            ->post(route('install.store'), [])
            ->assertRedirect(route('install.index'))
            ->assertSessionHasErrors([
                'site_name',
                'app_url',
                'db_host',
                'db_port',
                'db_database',
                'db_username',
                'admin_name',
                'admin_email',
                'admin_password',
            ]);
    }

    public function test_installer_rejects_short_admin_passwords(): void
    {
        $this->from(route('install.index'))
            ->post(route('install.store'), [
                'site_name' => 'Alom English Center',
                'app_url' => 'https://example.test',
                'db_host' => 'localhost',
                'db_port' => 3306,
                'db_database' => 'lms',
                'db_username' => 'lms',
                'admin_name' => 'Admin User',
                'admin_email' => 'admin@example.test',
                'admin_password' => 'short',
                'admin_password_confirmation' => 'short',
            ])
            ->assertRedirect(route('install.index'))
            ->assertSessionHasErrors('admin_password');
    }
}
