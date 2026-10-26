<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use App\Support\PrivateGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_guests_cannot_reach_the_private_gallery(): void
    {
        $this->get('/private')->assertRedirect('/login');
        $this->post('/private/unlock', ['private_password' => 'x'])->assertRedirect('/login');
        $this->postJson('/private/photos', [])->assertStatus(401);
    }

    public function test_the_setup_screen_is_shown_when_no_private_password_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/private')
            ->assertOk()
            ->assertSee('Set up your private gallery')
            ->assertDontSee('Private gallery is locked');
    }

    public function test_a_user_can_set_up_a_private_password_that_is_stored_hashed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/private/password', [
                'private_password' => 'super-secret',
                'private_password_confirmation' => 'super-secret',
            ])
            ->assertRedirect()
            ->assertSessionHas(PrivateGallery::SESSION_KEY, true);

        $user->refresh();

        $this->assertNotNull($user->private_password);
        $this->assertNotSame('super-secret', $user->private_password);
        $this->assertTrue(Hash::check('super-secret', $user->private_password));
    }

    public function test_the_private_gallery_is_locked_before_unlocking(): void
    {
        $user = $this->userWithPrivatePassword();

        $this->actingAs($user)
            ->get('/private')
            ->assertOk()
            ->assertSee('Private gallery is locked');
    }

    public function test_a_user_can_unlock_with_the_correct_password(): void
    {
        $user = $this->userWithPrivatePassword('open-sesame');

        $this->actingAs($user)
            ->post('/private/unlock', ['private_password' => 'open-sesame'])
            ->assertRedirect(route('private'))
            ->assertSessionHas(PrivateGallery::SESSION_KEY, true);
    }

    public function test_unlocking_with_the_wrong_password_fails(): void
    {
        $user = $this->userWithPrivatePassword('open-sesame');

        $this->actingAs($user)
            ->post('/private/unlock', ['private_password' => 'nope'])
            ->assertSessionHasErrors('private_password')
            ->assertSessionMissing(PrivateGallery::SESSION_KEY);
    }

    public function test_a_user_can_lock_the_gallery_again(): void
    {
        $user = $this->userWithPrivatePassword();

        $this->actingAs($user)
            ->withSession([PrivateGallery::SESSION_KEY => true])
            ->post('/private/lock')
            ->assertRedirect()
            ->assertSessionMissing(PrivateGallery::SESSION_KEY);
    }

    public function test_private_photos_are_uploaded_into_the_private_gallery(): void
    {
        $user = $this->userWithPrivatePassword();

        $file = UploadedFile::fake()->image('secret.jpg', 320, 240);

        $this->actingAs($user)
            ->withSession([PrivateGallery::SESSION_KEY => true])
            ->postJson('/private/photos', ['photos' => [$file]])
            ->assertCreated();

        $photo = Photo::where('user_id', $user->id)->where('original_filename', 'secret.jpg')->firstOrFail();
        $this->assertTrue($photo->is_private);
        $this->assertFalse($photo->isArchived());
    }

    public function test_uploading_to_a_locked_private_gallery_is_forbidden(): void
    {
        $user = $this->userWithPrivatePassword();
        $file = UploadedFile::fake()->image('secret.jpg', 320, 240);

        $this->actingAs($user)
            ->postJson('/private/photos', ['photos' => [$file]])
            ->assertForbidden();
    }

    public function test_the_unlocked_gallery_shows_its_photos_and_upload_zone(): void
    {
        $user = $this->userWithPrivatePassword();
        $photo = $this->privatePhoto($user, 'kept.jpg');

        $this->actingAs($user)
            ->withSession([PrivateGallery::SESSION_KEY => true])
            ->get('/private')
            ->assertOk()
            ->assertSee(route('private.photos.store'))
            ->assertSee('data-photo-id="'.$photo->id.'"', false);
    }

    public function test_private_photos_are_hidden_from_the_dashboard_grid(): void
    {
        $user = User::factory()->create();
        $normal = $this->upload($user, 'normal.jpg');
        $private = $this->privatePhoto($user, 'hidden.jpg');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-photo-id="'.$normal->id.'"', false)
            ->assertDontSee('data-photo-id="'.$private->id.'"', false);
    }

    public function test_private_photos_are_hidden_from_insights(): void
    {
        $user = User::factory()->create();
        $this->upload($user, 'visible-here.jpg');
        $this->privatePhoto($user, 'secret-insights.jpg');

        $this->actingAs($user)
            ->get('/insights')
            ->assertOk()
            ->assertSee('visible-here.jpg')
            ->assertDontSee('secret-insights.jpg');
    }

    public function test_private_photos_are_excluded_from_the_library_storage_totals(): void
    {
        $user = User::factory()->create();
        $this->upload($user, 'normal.jpg');

        $private = $this->privatePhoto($user, 'hidden.jpg');
        $private->size_bytes = 5_000_000;
        $private->save();

        $this->assertGreaterThanOrEqual(5_000_000, $user->storageUsedBytes());
        $this->assertSame($user->storageUsedBytes() - 5_000_000, $user->libraryStorageUsedBytes());
    }

    public function test_private_photo_files_are_forbidden_until_the_gallery_is_unlocked(): void
    {
        $user = $this->userWithPrivatePassword();
        $photo = $this->privatePhoto($user, 'locked.jpg');
        Storage::disk('public')->put($photo->file_path, 'binary-data');

        $this->actingAs($user)->get("/photos/{$photo->id}/raw")->assertForbidden();
        $this->actingAs($user)->get("/photos/{$photo->id}/thumbnail")->assertForbidden();
        $this->actingAs($user)->get("/photos/{$photo->id}")->assertForbidden();

        $this->actingAs($user)
            ->withSession([PrivateGallery::SESSION_KEY => true])
            ->get("/photos/{$photo->id}/raw")
            ->assertOk();
    }

    public function test_resetting_the_private_password_requires_the_account_password(): void
    {
        $user = User::factory()->create(['password' => 'account-pass']);
        $user->private_password = 'old-private';
        $user->save();

        $this->actingAs($user)
            ->post('/private/reset', [
                'current_password' => 'wrong',
                'private_password' => 'new-private',
                'private_password_confirmation' => 'new-private',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->post('/private/reset', [
                'current_password' => 'account-pass',
                'private_password' => 'new-private',
                'private_password_confirmation' => 'new-private',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('new-private', $user->fresh()->private_password));
    }

    public function test_changing_the_private_password_requires_the_current_one(): void
    {
        $user = $this->userWithPrivatePassword('old-private');

        $this->actingAs($user)
            ->post('/private/password', [
                'current_private_password' => 'wrong',
                'private_password' => 'new-private',
                'private_password_confirmation' => 'new-private',
            ])
            ->assertSessionHasErrors('current_private_password');

        $this->actingAs($user)
            ->post('/private/password', [
                'current_private_password' => 'old-private',
                'private_password' => 'new-private',
                'private_password_confirmation' => 'new-private',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('new-private', $user->fresh()->private_password));
    }

    public function test_the_private_tab_is_available_in_profile_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile/private')
            ->assertOk()
            ->assertSee('Private gallery')
            ->assertSee('Set private password');
    }

    public function test_the_private_gallery_link_appears_in_the_navigation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(route('private'));
    }

    public function test_the_unlock_gate_dialog_is_present_on_other_pages_while_locked(): void
    {
        $user = $this->userWithPrivatePassword();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('id="private-gate-dialog"', false)
            ->assertSee('data-private-gate', false)
            ->assertSee('Forgot your private password?');
    }

    public function test_the_unlock_gate_dialog_is_absent_when_not_locked(): void
    {
        $user = $this->userWithPrivatePassword();

        $this->actingAs($user)
            ->withSession([PrivateGallery::SESSION_KEY => true])
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('id="private-gate-dialog"', false);
    }

    public function test_the_unlock_gate_dialog_is_absent_without_a_private_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('id="private-gate-dialog"', false);
    }

    public function test_the_private_settings_tab_opens_the_unlock_gate(): void
    {
        $user = $this->userWithPrivatePassword();

        $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="tab-private"[^>]*data-private-gate/', $html);
    }

    public function test_private_settings_forms_are_absent_while_the_gallery_is_locked(): void
    {
        $user = $this->userWithPrivatePassword();

        $this->actingAs($user)
            ->get('/profile/private')
            ->assertRedirect(route('profile'));

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertDontSee('profile__panel--private', false)
            ->assertDontSee('Change private password')
            ->assertDontSee('id="current_private_password"', false)
            ->assertDontSee('id="reset_current_password"', false)
            ->assertDontSee('id="private_password_confirmation"', false);

        $this->actingAs($user)
            ->get('/profile?tab=private')
            ->assertRedirect(route('profile'));
    }

    public function test_private_settings_forms_are_present_after_unlocking(): void
    {
        $user = $this->userWithPrivatePassword();

        $this->actingAs($user)
            ->withSession([PrivateGallery::SESSION_KEY => true])
            ->get('/profile/private')
            ->assertOk()
            ->assertSee('Change private password')
            ->assertSee('Current private password')
            ->assertSee('Reset private password')
            ->assertDontSee('id="private-gate-dialog"', false);
    }

    public function test_a_locked_private_tab_returns_to_the_previous_settings_tab(): void
    {
        $user = $this->userWithPrivatePassword();

        $this->actingAs($user)
            ->from('/profile/privacy')
            ->get('/profile/private')
            ->assertRedirect(route('profile.privacy'));
    }

    public function test_the_unlock_gate_dialog_is_not_duplicated_on_the_private_page(): void
    {
        $user = $this->userWithPrivatePassword();

        $this->actingAs($user)
            ->get('/private')
            ->assertOk()
            ->assertDontSee('id="private-gate-dialog"', false);
    }

    private function userWithPrivatePassword(string $password = 'secret-pass'): User
    {
        return User::factory()->create(['private_password' => $password]);
    }

    private function upload(User $user, string $name): Photo
    {
        $file = UploadedFile::fake()->image($name, 320, 240);

        $this->actingAs($user)->postJson('/photos', ['photos' => [$file]]);

        return Photo::where('user_id', $user->id)->where('original_filename', $name)->firstOrFail();
    }

    private function privatePhoto(User $user, string $name = 'secret.jpg'): Photo
    {
        return Photo::create([
            'user_id' => $user->id,
            'file_path' => "photos/{$user->id}/{$name}",
            'original_filename' => $name,
            'size_bytes' => 1234,
            'mime_type' => 'image/jpeg',
            'is_private' => true,
        ]);
    }
}
