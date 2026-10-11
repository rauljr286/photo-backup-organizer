<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_the_welcome_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('welcome');

        $response->assertSee('id="landing-screen"', false);
        $response->assertSee('id="landing-get-started"', false);
        $response->assertSee('Get Started', false);
        $response->assertSee('href="'.route('login').'"', false);
        $response->assertSee('Your library stays yours.', false);
    }

    public function test_the_landing_markup_is_not_nested_in_the_auth_shell(): void
    {
        $response = $this->get('/');

        // The welcome page uses its own full-page layout, so the narrow
        // centered auth card wrapper must not appear at all.
        $response->assertDontSee('<div class="auth-shell">', false);
    }

    public function test_the_welcome_page_includes_the_compiled_css_and_js_tags(): void
    {
        $this->withVite();

        $response = $this->get('/');

        $response->assertSee('assets/app-', false);
        $response->assertSee('rel="stylesheet"', false);
        $response->assertSee('type="module"', false);
    }

    public function test_the_embedded_sign_in_panel_is_shown_after_a_failed_login(): void
    {
        $this->from('/')->post(route('login.attempt'), [
            'identifier' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/');

        $response = $this->get('/');

        $response->assertOk();
        // The auth panel is revealed (no `hidden`) and the landing is hidden.
        $response->assertSee('<div class="welcome-auth" id="auth-container">', false);
        $response->assertSee('<div class="landing-screen" id="landing-screen" hidden>', false);
        $response->assertSee('data-state="login"', false);
        $response->assertSeeText('The email address or mobile number and password you entered is incorrect.');
    }

    public function test_the_landing_page_shows_a_footer_with_legal_links(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('class="site-footer"', false);
        $response->assertDontSee('class="site-footer__copy"', false);
        $response->assertDontSee('class="social-links"', false);
        $response->assertDontSee('aria-label="Facebook"', false);
        $response->assertSee('href="'.route('terms').'"', false);
        $response->assertSee('href="'.route('privacy').'"', false);
    }

    public function test_the_sign_in_and_registration_pages_do_not_show_the_landing(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertDontSee('id="landing-screen"', false);

        $this->get('/register')
            ->assertOk()
            ->assertSee('Before you create your account')
            ->assertDontSee('id="landing-screen"', false);
    }
}
