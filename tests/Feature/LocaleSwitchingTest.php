<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_switch_to_bangla_and_keep_locale_in_session(): void
    {
        $this->from('/about')
            ->post('/language', ['locale' => 'bn'])
            ->assertRedirect('/about');

        $this->get('/about')
            ->assertOk()
            ->assertSee('lang="bn"', false)
            ->assertSee('আমাদের সম্পর্কে');
    }

    public function test_only_supported_locales_can_be_selected(): void
    {
        $this->from('/')
            ->post('/language', ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }
}
