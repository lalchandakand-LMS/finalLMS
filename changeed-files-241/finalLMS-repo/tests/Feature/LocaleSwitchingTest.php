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
    public function test_bangla_catalog_contains_every_english_translation_key(): void
    {
        $english = json_decode(file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $bangla = json_decode(file_get_contents(lang_path('bn.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([], array_diff_key($english, $bangla));
    }

}
