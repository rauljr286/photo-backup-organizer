<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create();
    }

    private function avatarFiledPath(): string
    {
        return 'public/'.$this->user->fresh()->avatar_path;
    }

    public function test_a_user_can_upload_an_avatar(): void
    {
        $file = UploadedFile::fake()->image('profile.jpg', 400, 300);

        $response = $this->actingAs($this->user)
            ->from('/settings?tab=profile')
            ->post('/settings/avatar', ['avatar' => $file]);

        $response->assertRedirect('/settings?tab=profile');

        $this->user->refresh();
        $this->assertNotNull($this->user->avatar_path);
        $this->assertStringStartsWith(
            'avatars/'.$this->user->id.'-',
            $this->user->avatar_path
        );
        $this->assertStringEndsWith('.png', $this->user->avatar_path);

        Storage::disk('public')->assertExists($this->user->avatar_path);

        $fullPath = Storage::disk('public')->path($this->user->avatar_path);
        $size = getimagesize($fullPath);
        $this->assertSame(256, $size[0], 'avatar width should be resized to 256px');
        $this->assertSame(256, $size[1], 'avatar height should be resized to 256px');
    }

    public function test_avatar_upload_rejects_non_images(): void
    {
        $file = UploadedFile::fake()->create('notes.txt', 100, 'text/plain');

        $response = $this->actingAs($this->user)
            ->post('/settings/avatar', ['avatar' => $file]);

        $response->assertSessionHasErrors('avatar');
        $this->assertNull($this->user->fresh()->avatar_path);
    }

    public function test_avatar_upload_rejects_oversized_files(): void
    {
        $file = UploadedFile::fake()->image('huge.jpg', 2000, 2000)->size(6000);

        $response = $this->actingAs($this->user)
            ->post('/settings/avatar', ['avatar' => $file]);

        $response->assertSessionHasErrors('avatar');
        $this->assertNull($this->user->fresh()->avatar_path);
    }

    public function test_replacing_an_avatar_removes_the_previous_file(): void
    {
        $this->actingAs($this->user)->post('/settings/avatar', [
            'avatar' => UploadedFile::fake()->image('one.png'),
        ]);

        $first = $this->user->fresh()->avatar_path;
        Storage::disk('public')->assertExists($first);

        $response = $this->actingAs($this->user)->post('/settings/avatar', [
            'avatar' => UploadedFile::fake()->image('two.jpg'),
        ]);

        $response->assertSessionHasNoErrors();

        $second = $this->user->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_the_api_profile_payload_includes_the_avatar_url(): void
    {
        $this->actingAs($this->user)->post('/settings/avatar', [
            'avatar' => UploadedFile::fake()->image('me.png'),
        ]);

        $this->user->refresh();

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('user.avatar_url', route('avatar', ['user' => $this->user->id], absolute: true));
    }
}
