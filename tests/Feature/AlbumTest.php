<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlbumTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->owner = User::factory()->create();
    }

    private function photo(): Photo
    {
        $file = UploadedFile::fake()->image('pic.jpg', 100, 100);
        $this->actingAs($this->owner)->postJson('/photos', ['photos' => [$file]]);

        return Photo::where('user_id', $this->owner->id)->firstOrFail();
    }

    public function test_create_rename_and_delete_album(): void
    {
        $this->actingAs($this->owner)
            ->post('/albums', ['name' => 'Summer 2026'])
            ->assertValid();

        $album = Album::where('user_id', $this->owner->id)->firstOrFail();
        $this->assertSame('Summer 2026', $album->name);

        $this->actingAs($this->owner)
            ->from("/albums/{$album->id}")
            ->put("/albums/{$album->id}", ['name' => 'Summer 2027'])
            ->assertRedirect("/albums/{$album->id}");
        $this->assertSame('Summer 2027', $album->fresh()->name);

        $photo = $this->photo();
        $album->photos()->attach($photo);

        $this->actingAs($this->owner)->delete("/albums/{$album->id}")->assertRedirect();

        $this->assertDatabaseMissing('albums', ['id' => $album->id]);
        $this->assertDatabaseHas('photos', ['id' => $photo->id]);
    }

    public function test_photos_can_be_added_and_removed_from_an_album(): void
    {
        $album = Album::create(['user_id' => $this->owner->id, 'name' => 'Trip']);
        $photo = $this->photo();

        $this->actingAs($this->owner)
            ->post("/albums/{$album->id}/photos", ['photo_id' => $photo->id])
            ->assertRedirect();
        $this->assertTrue($album->photos()->whereKey($photo->id)->exists());

        $this->actingAs($this->owner)
            ->delete("/albums/{$album->id}/photos", ['photo_id' => $photo->id])
            ->assertRedirect();

        $this->assertFalse($album->photos()->whereKey($photo->id)->exists());
    }

    public function test_an_album_can_be_downloaded_as_a_zip(): void
    {
        $album = Album::create(['user_id' => $this->owner->id, 'name' => 'Trip']);
        $photo = $this->photo();
        $album->photos()->attach($photo);

        $this->actingAs($this->owner)
            ->get("/albums/{$album->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');
    }

    public function test_album_show_renders_its_photos(): void
    {
        $album = Album::create(['user_id' => $this->owner->id, 'name' => 'Trip']);
        $photo = $this->photo();
        $album->photos()->attach($photo);

        $this->actingAs($this->owner)->get("/albums/{$album->id}")
            ->assertOk()
            ->assertSee('Trip')
            ->assertSee('pic.jpg');
    }
}
