<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoRenameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_a_user_can_rename_their_own_photo(): void
    {
        $user = User::factory()->create();
        $photo = $this->upload($user, 'beach.jpg');

        $this->actingAs($user)
            ->patchJson("/photos/{$photo->id}", ['original_filename' => 'sunrise.jpg'])
            ->assertOk()
            ->assertJsonPath('photo.original_filename', 'sunrise.jpg');

        $this->assertSame('sunrise.jpg', $photo->fresh()->original_filename);
    }

    public function test_a_rename_keeps_the_original_extension(): void
    {
        $user = User::factory()->create();
        $photo = $this->upload($user, 'beach.jpg');

        $this->actingAs($user)
            ->patchJson("/photos/{$photo->id}", ['original_filename' => 'sunrise.png'])
            ->assertOk();

        $this->assertSame('sunrise.jpg', $photo->fresh()->original_filename);
    }

    public function test_a_rename_strips_directory_components(): void
    {
        $user = User::factory()->create();
        $photo = $this->upload($user, 'beach.jpg');

        $this->actingAs($user)
            ->patchJson("/photos/{$photo->id}", ['original_filename' => '../../etc/passwd.jpg'])
            ->assertOk();

        $this->assertSame('passwd.jpg', $photo->fresh()->original_filename);
    }

    public function test_a_rename_cannot_escape_to_an_empty_name(): void
    {
        $user = User::factory()->create();
        $photo = $this->upload($user, 'beach.jpg');

        $this->actingAs($user)
            ->patchJson("/photos/{$photo->id}", ['original_filename' => '   '])
            ->assertOk();

        $this->assertSame('beach.jpg', $photo->fresh()->original_filename);
    }

    public function test_a_user_cannot_rename_another_users_photo(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $photo = $this->upload($owner, 'beach.jpg');

        $this->actingAs($intruder)
            ->patchJson("/photos/{$photo->id}", ['original_filename' => 'stolen.jpg'])
            ->assertForbidden();

        $this->assertSame('beach.jpg', $photo->fresh()->original_filename);
    }

    private function upload(User $user, string $name): Photo
    {
        $file = UploadedFile::fake()->image($name, 320, 240);

        $this->actingAs($user)->postJson('/photos', ['photos' => [$file]]);

        return Photo::where('user_id', $user->id)
            ->where('original_filename', $name)
            ->firstOrFail();
    }
}
