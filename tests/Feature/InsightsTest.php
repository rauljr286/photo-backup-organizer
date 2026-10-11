<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_guests_are_redirected_from_insights_and_cleanup(): void
    {
        $this->get('/insights')->assertRedirect('/login');
        $this->get('/cleanup')->assertRedirect('/login');
    }

    public function test_insights_page_summarises_the_library(): void
    {
        $user = User::factory()->create();

        $this->upload($user, 'one.jpg', 320, 240);
        $tagged = $this->upload($user, 'two.jpg', 321, 240);
        $tagged->tags = ['beach', 'sun'];
        $tagged->save();

        $this->actingAs($user)
            ->get('/insights')
            ->assertOk()
            ->assertSee('Photos')
            ->assertSee('Photos &amp; storage by year', false)
            ->assertSee('Busiest months')
            ->assertSee('Top tags')
            ->assertSee('#beach');
    }

    public function test_insights_page_renders_when_there_are_no_photos(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/insights')
            ->assertOk()
            ->assertSee('No photos uploaded yet.');
    }

    public function test_cleanup_page_flags_duplicate_photos_and_largest_files(): void
    {
        $user = User::factory()->create();

        $original = $this->upload($user, 'dup-a.jpg', 320, 240);

        $duplicate = new Photo([
            'user_id' => $user->id,
            'file_path' => $original->file_path,
            'original_filename' => 'dup-b.jpg',
            'file_hash' => $original->file_hash,
            'size_bytes' => $original->size_bytes,
            'mime_type' => 'image/jpeg',
        ]);
        $duplicate->save();

        $this->actingAs($user)
            ->get('/cleanup')
            ->assertOk()
            ->assertSee('Largest photos')
            ->assertSee('Duplicate photos')
            ->assertSee('dup-b.jpg')
            ->assertDontSee('No duplicates found.');
    }

    public function test_cleanup_page_reports_no_duplicates_for_unique_photos(): void
    {
        $user = User::factory()->create();
        $this->upload($user, 'unique.jpg', 320, 240);

        $this->actingAs($user)
            ->get('/cleanup')
            ->assertOk()
            ->assertSee('No duplicates found.');
    }

    private function upload(User $user, string $name, int $width, int $height): Photo
    {
        $file = UploadedFile::fake()->image($name, $width, $height);

        $this->actingAs($user)->postJson('/photos', ['photos' => [$file]]);

        return Photo::where('user_id', $user->id)->where('original_filename', $name)->firstOrFail();
    }
}
