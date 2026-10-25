<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use App\Support\PrivateGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InfiniteScrollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->owner = User::factory()->create();
    }

    public function test_the_dashboard_scrolls_instead_of_showing_numbered_pages(): void
    {
        $this->makePhotos($this->owner, 26);

        $response = $this->actingAs($this->owner)->get('/dashboard');

        $response->assertOk()
            ->assertSee('data-infinite-scroll', false)
            ->assertSee('data-next-page="2"', false)
            ->assertSee('/photos/page', false)
            ->assertDontSee('aria-label="Photo pages"', false)
            ->assertDontSee('>Next<', false);
    }

    public function test_the_dashboard_shows_no_scroller_when_everything_fits_on_one_page(): void
    {
        $this->makePhotos($this->owner, 3);

        $this->actingAs($this->owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('data-infinite-scroll', false);
    }

    public function test_the_page_endpoint_returns_the_next_page_of_photos(): void
    {
        $this->makePhotos($this->owner, 26);

        $first = $this->actingAs($this->owner)->getJson('/photos/page?page=1');

        $first->assertOk()
            ->assertJsonCount(24, 'photos')
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('next_page', 2)
            ->assertJsonPath('total', 26);

        $second = $this->actingAs($this->owner)->getJson('/photos/page?page=2');

        $second->assertOk()
            ->assertJsonCount(2, 'photos')
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('next_page', 3)
            ->assertJsonPath('total', 26);

        $pageOneIds = $first->json('photos.*.id');
        $pageTwoIds = $second->json('photos.*.id');

        $this->assertSame([], array_intersect($pageOneIds, $pageTwoIds));
        $this->assertSame(26, collect($pageOneIds)->merge($pageTwoIds)->unique()->count());
    }

    public function test_the_page_endpoint_keeps_the_album_filter(): void
    {
        $album = Album::create(['user_id' => $this->owner->id, 'name' => 'Trip']);
        $inAlbum = $this->makePhotos($this->owner, 30);
        $album->photos()->attach($inAlbum->take(3)->pluck('id'));

        $response = $this->actingAs($this->owner)
            ->getJson('/photos/page?page=1&album='.$album->id);

        $response->assertOk()->assertJsonCount(3, 'photos');
    }

    public function test_the_page_endpoint_keeps_the_search_filter(): void
    {
        $this->makePhotos($this->owner, 26);
        $this->makePhotos($this->owner, 2, 'beach-');

        $response = $this->actingAs($this->owner)
            ->getJson('/photos/page?page=1&search=beach');

        $response->assertOk()->assertJsonCount(2, 'photos');
    }

    public function test_guests_cannot_use_the_page_endpoint(): void
    {
        $this->getJson('/photos/page')->assertStatus(401);
    }

    public function test_the_private_page_endpoint_is_locked_until_the_gallery_is_unlocked(): void
    {
        $user = User::factory()->create(['private_password' => 'secret-pass']);

        $this->actingAs($user)
            ->getJson('/private/photos/page')
            ->assertStatus(403)
            ->assertJsonPath('has_more', false);

        $this->actingAs($user)
            ->withSession([PrivateGallery::SESSION_KEY => true])
            ->getJson('/private/photos/page')
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('total', 0);
    }

    public function test_the_private_page_endpoint_returns_unlocked_private_photos(): void
    {
        $user = User::factory()->create(['private_password' => 'secret-pass']);

        for ($i = 0; $i < 26; $i++) {
            Photo::create([
                'user_id' => $user->id,
                'file_path' => "photos/{$user->id}/secret-{$i}.jpg",
                'original_filename' => "secret-{$i}.jpg",
                'size_bytes' => 512,
                'mime_type' => 'image/jpeg',
                'is_private' => true,
                'taken_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($user)
            ->withSession([PrivateGallery::SESSION_KEY => true])
            ->getJson('/private/photos/page?page=1');

        $response->assertOk()
            ->assertJsonCount(24, 'photos')
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('next_page', 2);
    }

    /**
     * @return Collection<int, Photo>
     */
    private function makePhotos(User $user, int $count, string $prefix = 'photo-')
    {
        $photos = collect();

        for ($i = 0; $i < $count; $i++) {
            $photos->push(Photo::create([
                'user_id' => $user->id,
                'file_path' => "photos/{$user->id}/{$prefix}{$i}.jpg",
                'original_filename' => "{$prefix}{$i}.jpg",
                'size_bytes' => 1234,
                'mime_type' => 'image/jpeg',
                'taken_at' => now()->subMinutes($i),
            ]));
        }

        return $photos;
    }
}
