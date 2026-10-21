<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_profile_pages_require_authentication(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/profile/security')->assertRedirect('/login');
        $this->get('/profile/privacy')->assertRedirect('/login');
    }

    public function test_profile_security_and_privacy_pages_render(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Account settings')
            ->assertSee('Profile picture');

        $this->actingAs($user)
            ->get('/profile/security')
            ->assertOk()
            ->assertSee('Change your password')
            ->assertSee('Update password')
            ->assertDontSee('Log out other sessions');

        $this->actingAs($user)
            ->get('/profile/privacy')
            ->assertOk()
            ->assertSee('Privacy &amp; your data', false)
            ->assertSee('Request account deletion');
    }

    public function test_the_legacy_settings_route_still_renders_the_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertSee('Account settings');
    }

    public function test_the_profile_tab_matches_the_reference_layout(): void
    {
        $user = User::factory()->create();
        Album::create(['user_id' => $user->id, 'name' => 'Vacation']);
        $this->upload($user, 'active.jpg', 320, 240);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk()
            ->assertSee('profile-page', false)
            ->assertSee('Your profile')
            ->assertSee('Account settings')
            ->assertSee('Profile')
            ->assertSee('Security')
            ->assertSee('Privacy &amp; data', false)
            ->assertSee('Profile picture')
            ->assertSee('Upload photo')
            ->assertSee('Name')
            ->assertSee('Email')
            ->assertSee('Storage plan')
            ->assertSee('1 photo(s)')
            ->assertSee('1 album(s)')
            ->assertSee('Member since')
            ->assertSee('You can request full account deletion in the Security tab at any time.')
            ->assertSee('role="tabpanel"', false);
    }

    public function test_the_privacy_tab_matches_the_reference_layout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile/privacy');

        $response->assertOk()
            ->assertSee('profile-page', false)
            ->assertSee('profile__panel--privacy', false)
            ->assertSee('Privacy &amp; your data', false)
            ->assertSee('Privacy Policy')
            ->assertSee('Terms and Conditions')
            ->assertSee('Delete your account and all photos')
            ->assertSee('This permanently deletes your account, all of your photos, albums and any pending uploads.')
            ->assertSee('Request account deletion')
            ->assertSee('profile__danger', false);
    }

    public function test_profile_reports_the_photo_breakdown(): void
    {
        $user = User::factory()->create();
        $this->upload($user, 'active.jpg', 320, 240);
        $this->upload($user, 'archived.jpg', 420, 240)->archive();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('2 photo(s)');
    }

    public function test_an_account_deletion_request_does_not_delete_anything(): void
    {
        $user = User::factory()->create();
        $this->upload($user, 'keep-me.jpg', 320, 240);

        $response = $this->actingAs($user)
            ->from('/profile/privacy')
            ->post('/profile/deletion-request', ['confirm' => '1']);

        $response->assertRedirect('/profile/privacy');
        $response->assertSessionHas('status');

        $this->assertNotNull($user->fresh()->deletion_requested_at);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('photos', [
            'user_id' => $user->id,
            'original_filename' => 'keep-me.jpg',
            'deleted_at' => null,
        ]);
    }

    public function test_an_account_deletion_request_requires_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/deletion-request', [])
            ->assertSessionHasErrors('confirm');

        $this->assertNull($user->fresh()->deletion_requested_at);
    }

    public function test_the_privacy_page_shows_an_open_deletion_request(): void
    {
        $user = User::factory()->create();
        $user->requestDeletion();

        $this->actingAs($user)
            ->get('/profile/privacy')
            ->assertOk()
            ->assertSee('Account deletion requested')
            ->assertSee('Cancel deletion request');
    }

    public function test_an_account_deletion_request_can_be_cancelled(): void
    {
        $user = User::factory()->create();
        $user->requestDeletion();

        $this->actingAs($user)
            ->delete('/profile/deletion-request')
            ->assertSessionHas('status');

        $this->assertNull($user->fresh()->deletion_requested_at);
    }

    public function test_logging_out_other_sessions_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-staple']);

        $this->actingAs($user)
            ->post('/profile/logout-others', ['current_password' => 'not-my-password'])
            ->assertSessionHasErrors('current_password');
    }

    public function test_logging_out_other_sessions_succeeds_with_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-staple']);

        $response = $this->actingAs($user)
            ->from('/profile/security')
            ->post('/profile/logout-others', ['current_password' => 'correct-horse-staple']);

        $response->assertRedirect('/profile/security');
        $response->assertSessionHas('status');
    }

    private function upload(User $user, string $name, int $width, int $height): Photo
    {
        $file = UploadedFile::fake()->image($name, $width, $height);

        $this->actingAs($user)->postJson('/photos', ['photos' => [$file]]);

        return Photo::where('user_id', $user->id)
            ->where('original_filename', $name)
            ->firstOrFail();
    }
}
