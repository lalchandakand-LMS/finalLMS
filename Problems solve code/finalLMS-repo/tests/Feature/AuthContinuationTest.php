<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthContinuationTest extends TestCase
{
    public function test_login_page_remembers_a_safe_internal_course_destination(): void
    {
        $this->get('/login?continue=%2Fcourses%2Fspoken-english')
            ->assertOk()
            ->assertSee('Login securely');

        $this->assertSame('/courses/spoken-english', session('url.intended'));
    }

    public function test_login_page_does_not_remember_an_external_redirect_destination(): void
    {
        $this->get('/login?continue=https%3A%2F%2Fexample.com%2Fphishing')
            ->assertOk();

        $this->assertNull(session('url.intended'));
    }
}
