<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/albums')->assertRedirect('/login');
        $this->get('/archive')->assertRedirect('/login');
    }

    public function test_a_user_can_register_through_the_web_flow(): void
    {
        $this->post('/register/agree');

        $response = $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertAuthenticated();

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertGreaterThan(0, $user->storage_limit_mb);
    }

    public function test_a_user_must_agree_to_the_terms_before_registering(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('agree');
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
        $this->assertGuest();
    }

    public function test_agreeing_records_a_session_and_reveals_the_registration_form(): void
    {
        $response = $this->post('/register/agree');

        $response->assertRedirect(route('register'));
        $response->assertSessionHas('status');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create account')
            ->assertDontSee('data-terms-scroll');
    }

    public function test_the_agreement_timestamp_is_saved_when_the_account_is_created(): void
    {
        $this->post('/register/agree');

        $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertNotNull($user->agreed_to_terms_at);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'agreed_to_terms_at' => $user->agreed_to_terms_at,
        ]);
    }

    public function test_the_registration_page_shows_the_agreement_step_first(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create your account')
            ->assertSee('I Agree')
            ->assertSee('data-terms-scroll');
    }

    public function test_a_user_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        $this->post('/login', ['identifier' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_signing_in_does_not_land_on_a_thumbnail_url(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        // A browser that fires an image request while signed out (images left
        // on an open dashboard tab, for instance) leaves that file behind as
        // the "intended" destination for the next sign-in.
        $this->get('/photos/24/thumbnail')
            ->assertRedirect('/login')
            ->assertSessionHas('url.intended');

        $this->post('/login', [
            'identifier' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_signing_in_still_returns_the_user_to_the_page_they_asked_for(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        // Real pages are still honoured, so a guest who asked for a page they
        // were allowed to see lands back on it after signing in.
        $this->get('/albums')
            ->assertRedirect('/login')
            ->assertSessionHas('url.intended');

        $this->post('/login', [
            'identifier' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect(route('albums.index'));
    }

    public function test_login_returns_a_specific_error_for_bad_credentials(): void
    {
        $this->post('/login', ['identifier' => 'ghost@example.com', 'password' => 'whatever'])
            ->assertSessionHasErrors('identifier')
            ->assertSessionHas('errors');
    }

    public function test_api_register_returns_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'API User',
            'email' => 'api@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'user' => ['id', 'storage']]);
    }

    public function test_api_requires_a_token(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
        $this->getJson('/api/photos')->assertStatus(401);
    }

    public function test_api_login_and_profile(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        $login = $this->postJson('/api/login', [
            'identifier' => $user->email,
            'password' => 'Password123!',
        ]);

        $login->assertStatus(200)->assertJsonStructure(['token']);

        $token = $login->json('token');

        $this->getJson('/api/user', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->postJson('/api/logout', [], ['Authorization' => "Bearer {$token}"])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_is_rate_limited_after_five_failed_attempts_and_recovers(): void
    {
        $this->app['config']->set('cache.default', 'array');
        $user = User::factory()->create(['password' => 'Password123!']);

        foreach (range(1, 5) as $i) {
            $this->from(route('login'))
                ->post('/login', ['identifier' => $user->email, 'password' => 'wrong-password']);
        }

        // The 6th attempt is blocked even though the credentials are correct.
        $this->from(route('login'))
            ->post('/login', ['identifier' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('login'))->assertSee('Too many login attempts');

        // After the cooldown window passes, login works again.
        $this->travel(61)->seconds();
        $this->post('/login', ['identifier' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_successful_login_clears_failed_attempts(): void
    {
        $this->app['config']->set('cache.default', 'array');
        $user = User::factory()->create(['password' => 'Password123!']);

        foreach (range(1, 2) as $i) {
            $this->from(route('login'))
                ->post('/login', ['identifier' => $user->email, 'password' => 'wrong-password']);
        }

        // A correct login clears the limiter, so the next failure starts fresh.
        $this->post('/login', ['identifier' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect(route('dashboard'));

        $this->post('/logout');

        foreach (range(1, 5) as $i) {
            $this->from(route('login'))
                ->post('/login', ['identifier' => $user->email, 'password' => 'wrong-password']);
        }

        $this->from(route('login'))
            ->post('/login', ['identifier' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('identifier');
    }

    public function test_login_rate_limiting_is_per_account_and_ip(): void
    {
        $this->app['config']->set('cache.default', 'array');
        $user = User::factory()->create(['password' => 'Password123!']);
        $other = User::factory()->create(['password' => 'Password123!']);

        foreach (range(1, 5) as $i) {
            $this->from(route('login'))
                ->post('/login', ['identifier' => $user->email, 'password' => 'wrong-password']);
        }

        // A different account is not affected by the first account's failures.
        $this->post('/login', ['identifier' => $other->email, 'password' => 'Password123!'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_the_auth_card_renders_both_panels_with_sign_in_active(): void
    {
        $this->withSession(['agreed_to_terms_at' => now()]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('data-auth-card', false)
            ->assertSee('data-state="login"', false)
            ->assertSee('action="'.route('login.attempt').'"', false)
            ->assertSee('action="'.route('register.store').'"', false)
            ->assertSee('data-auth-switch="register"', false)
            ->assertSee('data-auth-switch="login"', false);
    }

    public function test_the_register_page_does_not_show_the_back_button(): void
    {
        $this->post('/register/agree');

        $this->get('/register')
            ->assertOk()
            ->assertDontSee('auth-card__back', false);
    }

    public function test_the_create_account_panel_shows_the_consent_step_before_agreement(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('data-auth-card', false)
            ->assertSee('data-state="login"', false)
            ->assertSee('data-terms-scroll', false)
            ->assertSee('I Agree');
    }

    public function test_the_create_account_panel_is_active_on_the_register_page(): void
    {
        $this->post('/register/agree');

        $this->get('/register')
            ->assertOk()
            ->assertSee('data-state="register"', false)
            ->assertSee('data-password-form', false)
            ->assertSee('data-password-submit', false)
            ->assertSee('data-password-match', false);
    }

    public function test_register_errors_render_in_the_create_account_panel_from_the_login_page(): void
    {
        $this->withSession(['agreed_to_terms_at' => now()]);

        $this->from('/login')->post('/register', [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            '_auth_pane' => 'register',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertSessionHasErrors(['first_name', 'last_name', 'email', 'phone', 'password']);

        // The failed submit sent the visitor back to /login, but the card must
        // reopen on the create-account panel with the errors visible.
        $this->get('/login')
            ->assertOk()
            ->assertSee('data-state="register"', false)
            ->assertSee('Create account');
    }

    public function test_a_user_can_register_with_an_email_only(): void
    {
        $this->post('/register/agree');

        $this->post('/register', [
            'name' => 'Email Only',
            'email' => 'emailonly@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            '_auth_pane' => 'register',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'emailonly@example.com')->firstOrFail();
        $this->assertNull($user->phone);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_user_can_register_with_a_mobile_number_only(): void
    {
        $this->post('/register/agree');

        $this->post('/register', [
            'name' => 'Mobile Only',
            'phone' => '09171234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            '_auth_pane' => 'register',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('phone', '09171234567')->firstOrFail();
        $this->assertNull($user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_user_can_register_with_both_email_and_mobile(): void
    {
        $this->post('/register/agree');

        $this->post('/register', [
            'name' => 'Both Details',
            'email' => 'both@example.com',
            'phone' => '+639171234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            '_auth_pane' => 'register',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'both@example.com')->firstOrFail();
        $this->assertSame('09171234567', $user->phone);
    }

    public function test_registration_requires_an_email_or_mobile_number(): void
    {
        $this->post('/register/agree');

        $this->post('/register', [
            'name' => 'Nowhere',
            'email' => '',
            'phone' => '',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            '_auth_pane' => 'register',
        ])->assertSessionHasErrors(['email', 'phone']);

        $this->assertGuest();
    }

    public function test_the_registration_form_renders_the_four_name_fields(): void
    {
        $this->post('/register/agree');

        $response = $this->get('/register')->assertOk();

        foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $response->assertSee('First name');
        $response->assertSee('Middle name');
        $response->assertSee('Last name');
        $response->assertSee('Suffix');
        // The common extensions are offered in the suffix dropdown.
        $response->assertSee('<option value="Jr."', false);
        $response->assertSee('<option value="III"', false);
    }

    public function test_a_user_can_register_with_structured_name_fields(): void
    {
        $this->post('/register/agree');

        $this->post('/register', [
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'suffix' => 'Jr.',
            'email' => 'juan@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            '_auth_pane' => 'register',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'juan@example.com')->firstOrFail();
        $this->assertSame('Juan Dela Cruz Jr.', $user->name);
        $this->assertAuthenticatedAs($user);
    }

    public function test_structured_registration_without_a_middle_name_or_suffix(): void
    {
        $this->post('/register/agree');

        $this->post('/register', [
            'first_name' => 'Maria',
            'middle_name' => '',
            'last_name' => 'Santos',
            'suffix' => '',
            'email' => 'maria@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            '_auth_pane' => 'register',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('Maria Santos', User::where('email', 'maria@example.com')->value('name'));
    }

    public function test_first_and_last_name_are_required_when_using_the_name_fields(): void
    {
        $this->post('/register/agree');

        $this->from('/register')->post('/register', [
            'first_name' => '',
            'middle_name' => 'Dela',
            'last_name' => '',
            'suffix' => '',
            'email' => 'nameless@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            '_auth_pane' => 'register',
        ])->assertSessionHasErrors(['first_name', 'last_name']);

        $this->assertDatabaseMissing('users', ['email' => 'nameless@example.com']);
        $this->assertGuest();
    }

    public function test_the_suffix_must_be_one_of_the_allowed_extensions(): void
    {
        $this->post('/register/agree');

        $this->from('/register')->post('/register', [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'suffix' => 'Sr. Jr. III',
            'email' => 'badsuffix@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            '_auth_pane' => 'register',
        ])->assertSessionHasErrors('suffix');

        $this->assertDatabaseMissing('users', ['email' => 'badsuffix@example.com']);
    }

    public function test_a_user_can_sign_in_with_a_mobile_number(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'phone' => '09171234567',
            'password' => 'Password123!',
        ]);

        $this->post('/login', ['identifier' => '09171234567', 'password' => 'Password123!'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_each_password_requirement_is_enforced(): void
    {
        $this->post('/register/agree');

        $cases = [
            'Ab1!',        // too short
            'abcdefg1!',   // no uppercase
            'ABCDEFG1!',   // no lowercase
            'Abcdefgh!',   // no number
            'Abcdefgh1',   // no special character
        ];

        foreach ($cases as $password) {
            $this->from('/register')->post('/register', [
                'name' => 'Rule Tester',
                'email' => 'rule-'.md5($password).'@example.com',
                'password' => $password,
                'password_confirmation' => $password,
                '_auth_pane' => 'register',
            ])->assertSessionHasErrors('password');
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_sign_in_screen_offers_google_and_facebook_buttons(): void
    {
        $this->configureSocialProvider('google');
        $this->configureSocialProvider('facebook');

        $response = $this->get('/login')->assertOk();

        $response->assertSee('Continue with Google');
        $response->assertSee('Continue with Facebook');
        $response->assertSee('href="'.route('social.redirect', 'google').'"', false);
        $response->assertSee('href="'.route('social.redirect', 'facebook').'"', false);

        // No other provider is offered.
        $response->assertDontSee('GitHub');
        $response->assertDontSee('LinkedIn');
    }

    public function test_social_buttons_are_hidden_when_no_provider_is_configured(): void
    {
        config()->set('services.google', ['client_id' => null, 'client_secret' => null]);
        config()->set('services.facebook', ['client_id' => null, 'client_secret' => null]);

        $response = $this->get('/login')->assertOk();

        $response->assertDontSee('Continue with Google');
        $response->assertDontSee('Continue with Facebook');
        $response->assertDontSee('class="auth-card__social"', false);
    }

    public function test_social_redirect_sends_the_visitor_to_the_provider(): void
    {
        $this->configureSocialProvider('google');

        $response = $this->get(route('social.redirect', 'google'));

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', (string) $response->headers->get('Location'));
    }

    public function test_social_redirect_is_unavailable_when_the_provider_is_not_configured(): void
    {
        config()->set('services.google', ['client_id' => null, 'client_secret' => null]);

        $this->get(route('social.redirect', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identifier');
    }

    public function test_an_unknown_social_provider_returns_not_found(): void
    {
        $this->get('/auth/github/redirect')->assertNotFound();
        $this->get('/auth/github/callback')->assertNotFound();
    }

    public function test_social_callback_creates_and_signs_in_a_new_user(): void
    {
        $this->mockSocialiteUser('google', '1122334455', 'jane.social@example.com', 'Jane Social');

        $this->get(route('social.callback', 'google'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'jane.social@example.com',
            'provider' => 'google',
            'provider_id' => '1122334455',
        ]);
    }

    public function test_social_callback_links_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->mockSocialiteUser('facebook', '998877', 'jane@example.com', 'Jane Doe');

        $this->get(route('social.callback', 'facebook'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('facebook', $user->fresh()->provider);
        $this->assertSame('998877', $user->fresh()->provider_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_social_callback_redirects_back_on_provider_failure(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('denied'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Mock the Socialite facade so the named provider returns a fixed profile.
     */
    private function mockSocialiteUser(string $providerName, string $id, ?string $email, ?string $name): void
    {
        $socialUser = (new SocialiteUser)->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'nickname' => null,
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with($providerName)->andReturn($provider);
    }

    /**
     * Give a provider a full set of OAuth credentials for the current test.
     */
    private function configureSocialProvider(string $name): void
    {
        config()->set("services.$name", [
            'client_id' => $name.'-client-id',
            'client_secret' => $name.'-client-secret',
            'redirect' => 'http://localhost/auth/'.$name.'/callback',
        ]);
    }
}
