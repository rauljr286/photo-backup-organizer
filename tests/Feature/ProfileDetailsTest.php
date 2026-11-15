<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_succeeds_without_optional_profile_details(): void
    {
        $this->post('/register/agree');

        $this->post('/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'no-details@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            '_auth_pane' => 'register',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'no-details@example.com')->firstOrFail();

        $this->assertNull($user->gender);
        $this->assertNull($user->birthdate);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_saves_optional_profile_details(): void
    {
        $this->post('/register/agree');

        $this->post('/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'with-details@example.com',
            'gender' => 'Female',
            'birthdate' => '1990-01-02',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            '_auth_pane' => 'register',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'with-details@example.com')->firstOrFail();

        $this->assertSame('Female', $user->gender);
        $this->assertSame('1990-01-02', $user->birthdate?->format('Y-m-d'));
    }

    public function test_api_registration_accepts_optional_profile_details(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'API Details',
            'email' => 'api-details@example.com',
            'gender' => 'Prefer not to say',
            'birthdate' => '1985-05-20',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'api-details@example.com')->firstOrFail();

        $this->assertSame('Prefer not to say', $user->gender);
        $this->assertSame('1985-05-20', $user->birthdate?->format('Y-m-d'));
    }

    public function test_a_user_can_update_their_profile_details(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile/details', [
                'gender' => 'Female',
                'birthdate' => '1990-01-02',
                'country' => 'Philippines',
                'province' => 'Cebu',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Lahug',
                'street' => '123 Mabini St',
            ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('status');

        $user->refresh();

        $this->assertSame('Female', $user->gender);
        $this->assertSame('1990-01-02', $user->birthdate?->format('Y-m-d'));
        $this->assertSame('Philippines', $user->country);
        $this->assertSame('Cebu', $user->province);
        $this->assertSame('Cebu City', $user->city_municipality);
        $this->assertSame('Lahug', $user->barangay);
        $this->assertSame('123 Mabini St', $user->street);
    }

    public function test_profile_details_validation_rejects_a_future_birthdate_and_invalid_gender(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile/details', [
                'gender' => 'Unknown',
                'birthdate' => now()->addDay()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors(['gender', 'birthdate']);

        $this->assertNull($user->fresh()->gender);
        $this->assertNull($user->fresh()->birthdate);
    }

    public function test_a_user_cannot_update_another_users_profile_details(): void
    {
        $owner = User::factory()->create(['gender' => 'Male']);
        $other = User::factory()->create(['gender' => 'Female']);

        $this->actingAs($other)
            ->from('/profile')
            ->patch('/profile/details', [
                'user_id' => $owner->id,
                'gender' => 'Other',
                'birthdate' => '1990-01-01',
                'country' => 'Philippines',
            ])
            ->assertRedirect('/profile')
            ->assertSessionHas('status');

        $this->assertSame('Male', $owner->fresh()->gender);
        $this->assertNull($owner->fresh()->country);
        $this->assertSame('Other', $other->fresh()->gender);
        $this->assertSame('Philippines', $other->fresh()->country);
    }

    public function test_the_profile_page_displays_gender_birthdate_and_formatted_address(): void
    {
        $user = User::factory()->create([
            'gender' => 'Female',
            'birthdate' => '1990-01-02',
            'country' => 'Philippines',
            'province' => 'Cebu',
            'city_municipality' => 'Cebu City',
            'barangay' => 'Lahug',
            'street' => '123 Mabini St',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Female')
            ->assertSee('January 2, 1990')
            ->assertSee('123 Mabini St, Lahug, Cebu City, Cebu, Philippines');
    }

    public function test_the_profile_page_shows_not_set_when_details_are_empty(): void
    {
        $user = User::factory()->create(['gender' => null, 'birthdate' => null]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Gender')
            ->assertSee('Birthdate')
            ->assertSee('Address')
            ->assertSee('Not set');
    }
}
