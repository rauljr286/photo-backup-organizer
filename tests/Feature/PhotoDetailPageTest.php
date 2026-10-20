<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoDetailPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_the_detail_page_renders_the_reference_layout(): void
    {
        $user = User::factory()->create();
        $photo = $this->upload($user, 'forest.jpg', 320, 240);

        $response = $this->actingAs($user)->get("/photos/{$photo->id}");

        $response->assertOk()
            ->assertSee('pd-page', false)
            ->assertSee('Download photo')
            ->assertSee('Archive')
            ->assertSee('Zoom out')
            ->assertSee('Zoom in')
            ->assertSee('Reset zoom')
            ->assertSee('100%')
            ->assertSee('Add to album')
            ->assertSee('Photo details')
            ->assertSee('Details &amp; tags', false)
            ->assertSee('Taken')
            ->assertSee('Uploaded')
            ->assertSee('Size')
            ->assertSee('Type')
            ->assertSee('Cloud backup')
            ->assertSee('Albums')
            ->assertSee('Tags (comma separated)')
            ->assertSee('Save changes')
            ->assertSee('Describes the photo for screen readers.')
            ->assertSee('id="details"', false)
            ->assertSee('id="albums"', false);
    }

    public function test_the_tab_navigation_marks_photos_as_active(): void
    {
        $user = User::factory()->create();
        $photo = $this->upload($user, 'forest.jpg', 320, 240);

        $this->actingAs($user)
            ->get("/photos/{$photo->id}")
            ->assertOk()
            ->assertSee('tabnav__link is-active', false);
    }

    public function test_album_buttons_toggle_between_add_and_in_states(): void
    {
        $user = User::factory()->create();
        $album = Album::create(['user_id' => $user->id, 'name' => 'Vacation']);
        $photo = $this->upload($user, 'forest.jpg', 320, 240);

        $this->actingAs($user)
            ->get("/photos/{$photo->id}")
            ->assertOk()
            ->assertSee('Add to &ldquo;Vacation&rdquo;', false);

        $album->photos()->attach($photo->id);

        $this->actingAs($user)
            ->get("/photos/{$photo->id}")
            ->assertOk()
            ->assertSee('In &ldquo;Vacation&rdquo;', false);
    }

    public function test_an_archived_photo_offers_unarchive_instead(): void
    {
        $user = User::factory()->create();
        $photo = $this->upload($user, 'forest.jpg', 320, 240);
        $photo->update(['archived_at' => now()]);

        $this->actingAs($user)
            ->get("/photos/{$photo->id}")
            ->assertOk()
            ->assertSee('Unarchive')
            ->assertSee(route('photos.unarchive', $photo), false);
    }

    public function test_the_details_card_shows_stored_metadata(): void
    {
        $user = User::factory()->create();
        $photo = $this->upload($user, 'forest.jpg', 320, 240);
        $photo->update([
            'size_bytes' => 2048,
            'remote_path' => 'backups/forest.jpg',
            'backed_up_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/photos/{$photo->id}")
            ->assertOk()
            ->assertSee('2 KB')
            ->assertSee('image/jpeg')
            ->assertSee('Backed up');
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
