<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The theme is resolved by an inline script in the document head so the
     * correct colour scheme is applied before the first paint.
     */
    public function test_the_welcome_page_contains_the_theme_bootstrap_and_toggle(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('pbo-theme', false);
        $response->assertSee("localStorage.getItem('pbo-theme')", false);
        $response->assertSee("setAttribute('data-theme'", false);
        $response->assertSee('data-theme-toggle', false);
        $response->assertSee('name="color-scheme"', false);
    }

    public function test_the_guest_auth_pages_contain_the_theme_bootstrap(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('pbo-theme', false);
        $response->assertSee("setAttribute('data-theme'", false);
        $response->assertSee('name="color-scheme"', false);
        // The theme is shared across the app, so the auth pages rely on the
        // resolved preference without rendering their own toggle.
        $response->assertDontSee('data-theme-toggle', false);
    }

    public function test_the_authenticated_dashboard_contains_the_theme_bootstrap_and_toggle(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('pbo-theme', false);
        $response->assertSee("setAttribute('data-theme'", false);
        $response->assertSee('data-theme-toggle', false);
        $response->assertSee('name="color-scheme"', false);
        // The navbar toggle, the toggle in the 768-899.98px bottom bar's More
        // panel, and the toggle in the phone (<=767.98px) More panel.
        $this->assertSame(3, substr_count($response->getContent(), 'data-theme-toggle'));
    }
}
