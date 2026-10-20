<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InsightsCleanupUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_insights_page_shows_the_monthly_chart(): void
    {
        $user = User::factory()->create();
        $this->upload($user, 'jpeg-one.jpg', 320, 240);

        $this->actingAs($user)
            ->get('/insights')
            ->assertOk()
            ->assertSee('Uploads, last 12 months')
            ->assertDontSee('File types');
    }

    public function test_uploads_chart_groups_by_upload_date_not_taken_date(): void
    {
        $user = User::factory()->create();
        $photo = $this->upload($user, 'shot-long-ago.jpg', 320, 240);

        $photo->taken_at = now()->subMonthsNoOverflow(5);
        $photo->save();

        $this->actingAs($user)
            ->get('/insights')
            ->assertOk()
            ->assertSee(now()->format('M Y').': 1 photo', false)
            ->assertSee(now()->subMonthsNoOverflow(5)->format('M Y').': 0 photos', false);
    }

    public function test_by_year_table_groups_by_upload_year_not_taken_year(): void
    {
        $user = User::factory()->create();
        $photo = $this->upload($user, 'old-shot.jpg', 320, 240);

        $photo->taken_at = now()->subYears(3);
        $photo->save();

        $this->actingAs($user)
            ->get('/insights')
            ->assertOk()
            ->assertSee('<strong>'.now()->year.'</strong>', false)
            ->assertDontSee('<strong>'.now()->subYears(3)->year.'</strong>', false);
    }

    public function test_cleanup_page_renders_every_category_tab(): void
    {
        $user = User::factory()->create();
        $this->upload($user, 'photo.jpg', 320, 240);

        $this->actingAs($user)
            ->get('/cleanup')
            ->assertOk()
            ->assertSee('Duplicate photos')
            ->assertSee('Large files')
            ->assertSee('Not in any album')
            ->assertSee('Missing description');
    }

    public function test_cleanup_lists_photos_that_are_not_in_an_album(): void
    {
        $user = User::factory()->create();
        $orphan = $this->upload($user, 'orphan.jpg', 320, 240);
        $filed = $this->upload($user, 'filed.jpg', 420, 240);

        $album = Album::create(['user_id' => $user->id, 'name' => 'Trip']);
        $album->photos()->attach($filed->id);

        $response = $this->actingAs($user)->get('/cleanup');

        $response->assertOk()
            ->assertSee('orphan.jpg')
            ->assertSee('Add to an album');
    }

    public function test_cleanup_flags_photos_without_a_description(): void
    {
        $user = User::factory()->create();
        $photo = $this->upload($user, 'undescribed.jpg', 320, 240);

        $this->actingAs($user)
            ->get('/cleanup')
            ->assertOk()
            ->assertSee('undescribed.jpg')
            ->assertSee('Automatic alt text');

        $photo->alt_text = 'A written description';
        $photo->save();

        $this->actingAs($user)
            ->get('/cleanup')
            ->assertOk()
            ->assertDontSee('Automatic alt text');
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
