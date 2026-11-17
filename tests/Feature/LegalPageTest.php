<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_read_the_terms_and_privacy_pages(): void
    {
        $this->get(route('terms'))
            ->assertOk()
            ->assertViewIs('legal.terms')
            ->assertSeeText('Terms and Conditions')
            ->assertSeeText('Last updated:');

        $this->get(route('privacy'))
            ->assertOk()
            ->assertViewIs('legal.privacy')
            ->assertSeeText('Privacy Policy')
            ->assertSeeText('How we handle your photos');
    }

    public function test_the_legal_pages_render_guest_navigation_without_the_account_menu(): void
    {
        $response = $this->get(route('terms'))->assertOk();

        $response->assertSee('href="'.route('login').'"', false);
        $response->assertSee('href="'.route('register').'"', false);
        $response->assertDontSee('aria-label="Account menu"', false);
        $response->assertDontSee('class="bottom-nav"', false);
    }

    public function test_an_authenticated_user_can_still_read_the_legal_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('terms'))->assertOk()->assertViewIs('legal.terms');
        $this->get(route('privacy'))->assertOk()->assertViewIs('legal.privacy');
    }
}
