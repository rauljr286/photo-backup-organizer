<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use App\Services\PhotoStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->owner = User::factory()->create();
        $this->stranger = User::factory()->create();
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('holiday-sunset.jpg', 640, 480);
    }

    public function test_a_user_can_upload_a_photo_from_the_dashboard(): void
    {
        $file = $this->image();

        $response = $this->actingAs($this->owner)->postJson('/photos', ['photos' => [$file]]);

        $response->assertCreated()->assertJsonStructure(['uploaded']);

        $this->assertDatabaseHas('photos', [
            'user_id' => $this->owner->id,
            'original_filename' => 'holiday-sunset.jpg',
        ]);

        $photo = Photo::where('user_id', $this->owner->id)->firstOrFail();
        Storage::disk('public')->assertExists($photo->file_path);
        $this->assertFalse($photo->isArchived());
        $this->assertNotEmpty($photo->description());
    }

    public function test_guests_cannot_upload(): void
    {
        $this->postJson('/photos', ['photos' => [$this->image()]])->assertStatus(401);
    }

    public function test_upload_rejects_non_image_files_with_a_per_file_error(): void
    {
        $bad = UploadedFile::fake()->create('evil.txt', 10);

        $response = $this->actingAs($this->owner)
            ->postJson('/photos', ['photos' => [$bad]]);

        $response->assertOk()
            ->assertJsonCount(1, 'failed')
            ->assertJsonPath('failed.0.error', fn (string $error) => str_contains($error, 'Unsupported format'))
            ->assertJsonCount(0, 'uploaded');

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_invalid_oversized_and_corrupted_files_are_rejected_independently(): void
    {
        $this->app['config']->set('photobackup.max_upload_mb', 1);

        $valid = UploadedFile::fake()->image('valid.jpg', 64, 64);
        $valid2 = UploadedFile::fake()->image('valid2.jpg', 65, 64);
        $oversized = UploadedFile::fake()->create('oversized.jpg', 2 * 1024);
        $corrupted = UploadedFile::fake()->create('broken.jpg', 64);
        $unsupported = UploadedFile::fake()->create('script.php', 64);

        $keys = ['ok1', 'ok2', 'size', 'corr', 'fmt'];

        $response = $this->actingAs($this->owner)->postJson('/photos', [
            'photos' => [$valid, $valid2, $oversized, $corrupted, $unsupported],
            'keys' => $keys,
        ]);

        $response->assertSuccessful()
            ->assertJsonCount(2, 'uploaded')
            ->assertJsonCount(3, 'failed');

        $response->assertJsonStructure([
            'uploaded' => [['key', 'id', 'url', 'description', 'original_filename']],
            'failed' => [['key', 'original_filename', 'error']],
        ]);

        $this->assertDatabaseCount('photos', 2);
        $this->assertDatabaseHas('photos', ['original_filename' => 'valid.jpg']);
        $this->assertDatabaseHas('photos', ['original_filename' => 'valid2.jpg']);
        $this->assertDatabaseMissing('photos', ['original_filename' => 'oversized.jpg']);

        $failed = collect($response->json('failed'));

        $this->assertSame('size', $failed->firstWhere('key', 'size')['key'] ?? 'missing');
        $this->assertStringContainsString('too large', strtolower($failed->firstWhere('key', 'size')['error'] ?? ''));
        $this->assertStringContainsString('corrupted', strtolower($failed->firstWhere('key', 'corr')['error'] ?? ''));
        $this->assertStringContainsString('unsupported', strtolower($failed->firstWhere('key', 'fmt')['error'] ?? ''));
    }

    public function test_a_large_batch_with_valid_and_invalid_files_succeeds_for_the_valid_ones(): void
    {
        $valid = [];
        $keys = [];
        foreach (range(1, 30) as $i) {
            $valid[] = UploadedFile::fake()->image("photo{$i}.jpg", 32 + ($i % 5), 32 + (($i + 1) % 7));
            $keys[] = "valid{$i}";
        }

        $oversized = UploadedFile::fake()->create('oversized.png', 2 * 1024);
        $bad = UploadedFile::fake()->create('readme.md', 32);

        $allFiles = array_merge($valid, [$oversized, $bad]);
        $allKeys = array_merge($keys, ['os1', 'bad1']);

        $response = $this->actingAs($this->owner)->postJson('/photos', [
            'photos' => $allFiles,
            'keys' => $allKeys,
        ]);

        $response->assertSuccessful();
        $this->assertCount(30, $response->json('uploaded'));
        $this->assertCount(2, $response->json('failed'));

        $this->assertDatabaseCount('photos', 30);
    }

    public function test_a_storage_failure_is_logged_and_does_not_abort_the_batch(): void
    {
        $logPath = storage_path('logs/laravel.log');
        file_put_contents($logPath, '');

        $good = UploadedFile::fake()->image('good.jpg', 64, 64);
        $also = UploadedFile::fake()->image('also.jpg', 64, 64);

        $mock = \Mockery::mock(PhotoStorageService::class)->makePartial();
        $mock->shouldReceive('store')
            ->once()
            ->with($this->callback(fn ($file) => $file->getClientOriginalName() === 'good.jpg'), $this->anything(), $this->anything(), $this->anything(), $this->anything())
            ->andThrow(new \RuntimeException('Disk write failed'));
        $this->instance(PhotoStorageService::class, $mock);

        $response = $this->actingAs($this->owner)->postJson('/photos', [
            'photos' => [$good, $also],
            'keys' => ['k1', 'k2'],
        ]);

        $response->assertSuccessful()
            ->assertJsonCount(1, 'uploaded')
            ->assertJsonCount(1, 'failed')
            ->assertJsonPath('failed.0.key', 'k1')
            ->assertJsonFragment(['original_filename' => 'good.jpg']);

        $this->assertDatabaseCount('photos', 1);

        $log = file_get_contents($logPath);
        $this->assertStringContainsString('Disk write failed', $log);
    }

    public function test_a_user_cannot_see_or_archive_another_users_photo(): void
    {
        $photo = $this->uploadFor($this->owner);

        $this->actingAs($this->stranger)->get("/photos/{$photo->id}")->assertStatus(403);
        $this->actingAs($this->stranger)->deleteJson("/photos/{$photo->id}")->assertStatus(403);
    }

    public function test_archive_and_unarchive_flow(): void
    {
        $photo = $this->uploadFor($this->owner);

        $this->actingAs($this->owner)
            ->deleteJson("/photos/{$photo->id}")
            ->assertOk()
            ->assertJson(['message' => 'Photo archived.']);

        $this->assertTrue($photo->fresh()->isArchived());

        $this->get('/archive')->assertViewHas('photos');
        $this->actingAs($this->owner)
            ->post("/photos/{$photo->id}/unarchive")
            ->assertRedirect();
        $this->assertFalse($photo->fresh()->isArchived());
    }

    public function test_archived_photos_are_hidden_from_the_dashboard_grid(): void
    {
        $photo = $this->uploadFor($this->owner);
        $this->actingAs($this->owner)->deleteJson("/photos/{$photo->id}");

        $this->actingAs($this->owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee(route('photos.thumbnail', $photo), false);
    }

    public function test_the_archive_page_lists_archived_photos_without_delete_options(): void
    {
        $photo = $this->uploadFor($this->owner);
        $this->actingAs($this->owner)->deleteJson("/photos/{$photo->id}");

        $this->actingAs($this->owner)
            ->get('/archive')
            ->assertOk()
            ->assertSee('Archived')
            ->assertSee("photos/{$photo->id}/unarchive")
            ->assertDontSee('Delete forever')
            ->assertDontSee('Please type DELETE to confirm.');
    }

    public function test_the_archive_page_can_select_photos_for_a_bulk_restore(): void
    {
        $photo = $this->uploadFor($this->owner);
        $this->actingAs($this->owner)->deleteJson("/photos/{$photo->id}");

        $response = $this->actingAs($this->owner)->get('/archive');

        $response->assertOk()
            // The card is selectable through the checkbox/long press; the
            // check button in the corner was removed from every card.
            ->assertSee('js-select-photo', false)
            ->assertDontSee('photo-card__pick', false)
            // The bulk bar restores the selection.
            ->assertSee('id="bulk-bar"', false)
            ->assertSee('id="restore-selected"', false)
            // The URL the bulk bar posts to.
            ->assertSee('data-restore-url="'.route('photos.unarchive', $photo).'"', false);
    }

    public function test_after_unarchiving_the_archive_page_is_clean(): void
    {
        $photo = $this->uploadFor($this->owner);
        $this->actingAs($this->owner)->deleteJson("/photos/{$photo->id}");
        $this->actingAs($this->owner)->post("/photos/{$photo->id}/unarchive");

        $this->actingAs($this->owner)
            ->get('/archive')
            ->assertOk()
            ->assertSee('Archive is empty')
            ->assertDontSee("photos/{$photo->id}/unarchive");

        $this->assertDatabaseHas('photos', ['id' => $photo->id]);
    }

    public function test_migrate_command_moves_trashed_photos_to_archived(): void
    {
        $photo = $this->uploadFor($this->owner);
        $photo->deleted_at = now()->subDays(10);
        $photo->save();

        $this->artisan('photos:migrate-trashed-to-archived')->assertExitCode(0);

        $fresh = $photo->fresh();
        $this->assertNotNull($fresh->archived_at);
        $this->assertNull($fresh->deleted_at);
    }

    public function test_api_can_archive_and_unarchive_a_photo(): void
    {
        $token = $this->owner->createToken('test')->plainTextToken;
        $photo = $this->uploadFor($this->owner);

        $this->withToken($token)
            ->deleteJson("/api/photos/{$photo->id}")
            ->assertOk()
            ->assertJson(['message' => 'Photo archived.']);

        $this->assertTrue($photo->fresh()->isArchived());

        $this->withToken($token)
            ->postJson("/api/photos/{$photo->id}/unarchive")
            ->assertOk()
            ->assertJson(['message' => 'Photo restored from archive.']);

        $this->assertFalse($photo->fresh()->isArchived());
    }

    public function test_api_index_excludes_archived_photos_by_default(): void
    {
        $token = $this->owner->createToken('test')->plainTextToken;
        $photo = $this->uploadFor($this->owner);
        $this->actingAs($this->owner)->deleteJson("/photos/{$photo->id}");

        $this->withToken($token)
            ->getJson('/api/photos')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_api_index_can_filter_to_only_archived_photos(): void
    {
        $token = $this->owner->createToken('test')->plainTextToken;
        $photo = $this->uploadFor($this->owner);
        $this->actingAs($this->owner)->deleteJson("/photos/{$photo->id}");

        $indexed = $this->withToken($token)
            ->getJson('/api/photos?archived=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0');

        $this->assertSame($photo->id, $indexed['id']);
        $this->assertNotNull($indexed['archived_at']);
    }

    public function test_filters_find_photos_by_search_and_tag(): void
    {
        $sunset = $this->uploadFor($this->owner, 'sunset.jpg');
        $sunset->tags = ['sunset', 'beach'];
        $sunset->save();

        $this->uploadFor($this->owner, 'dog.jpg', 321, 240);

        $service = app(PhotoStorageService::class);

        $search = $service->filteredPhotos($this->owner, ['search' => 'sunset'], 0);
        $this->assertCount(1, $search);

        $tagged = $service->filteredPhotos($this->owner, ['tags' => 'beach,missing'], 0);
        $this->assertCount(1, $tagged);

        $this->assertEqualsCanonicalizing(['beach', 'sunset'], $service->allTagsFor($this->owner));
    }

    public function test_backup_to_local_mirror_marks_photo_as_backed_up(): void
    {
        config(['photobackup.cloud_disk' => null]);

        $photo = $this->uploadFor($this->owner);

        app(PhotoStorageService::class)->backupToCloud($photo);

        $this->assertNotNull($photo->fresh()->backed_up_at);
        Storage::disk('local')->assertExists($photo->remote_path);
    }

    public function test_api_store_and_list_require_a_token(): void
    {
        $this->postJson('/api/photos', ['photos' => [$this->image()]])->assertStatus(401);
        $this->postJson('/api/albums', ['name' => 'Trip'])->assertStatus(401);
    }

    public function test_api_bulk_upload_reports_per_file_results(): void
    {
        $token = $this->owner->createToken('test')->plainTextToken;

        $valid = UploadedFile::fake()->image('api-ok.jpg', 64, 64);
        $bad = UploadedFile::fake()->create('api-bad.txt', 32);

        $response = $this->withToken($token)->postJson('/api/photos', [
            'photos' => [$valid, $bad],
            'keys' => ['a', 'b'],
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('failed.0.key', 'b')
            ->assertJsonPath('failed.0.error', fn (string $error) => str_contains($error, 'Unsupported format'))
            ->assertJsonCount(1, 'uploaded');

        $this->assertDatabaseHas('photos', ['original_filename' => 'api-ok.jpg']);
    }

    public function test_hashes_are_backfilled_by_the_backfill_command(): void
    {
        $photo = $this->uploadFor($this->owner);
        $photo->update(['file_hash' => null]);

        $this->artisan('photos:backfill-hashes')->assertExitCode(0);

        $this->assertNotNull($photo->fresh()->file_hash);
        $this->assertSame(64, strlen((string) $photo->fresh()->file_hash));
    }

    public function test_upload_creates_a_thumbnail_used_by_the_dashboard_grid(): void
    {
        $photo = $this->uploadFor($this->owner);

        $this->assertNotNull($photo->fresh()->thumbnail_path);
        Storage::disk('public')->assertExists($photo->fresh()->thumbnail_path);
        Storage::disk('public')->assertExists($photo->file_path);

        $this->actingAs($this->owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(route('photos.thumbnail', $photo), false);
    }

    public function test_the_dashboard_photo_card_has_no_per_photo_buttons(): void
    {
        $photo = $this->uploadFor($this->owner);

        $response = $this->actingAs($this->owner)->get('/dashboard')->assertOk();

        // Archiving is done from the selection toolbar, so the card itself is
        // click-to-open only: no "View & zoom" link and no Archive button.
        $response->assertDontSee('View &amp; zoom', false);
        $response->assertDontSee('js-archive-photo', false);
        $response->assertSee('js-select-photo', false);

        // The card still advertises where to archive it — that is the URL the
        // bulk toolbar reads when archiving a selection.
        $response->assertSee('data-archive-url="'.route('photos.archive', $photo).'"', false);
        $response->assertSee('data-restore-url="'.route('photos.unarchive', $photo).'"', false);
    }

    public function test_thumbnail_route_falls_back_to_the_original_when_no_thumbnail_exists(): void
    {
        $photo = $this->uploadFor($this->owner);
        Storage::disk('public')->delete($photo->thumbnail_path);
        $photo->update(['thumbnail_path' => null]);

        $this->actingAs($this->owner)
            ->get(route('photos.thumbnail', $photo))
            ->assertOk();
    }

    public function test_thumbnails_are_backfilled_by_the_generate_command(): void
    {
        $photo = $this->uploadFor($this->owner);
        Storage::disk('public')->delete($photo->thumbnail_path);
        $photo->update(['thumbnail_path' => null]);

        $this->artisan('photos:generate-thumbnails')->assertExitCode(0);

        $this->assertNotNull($photo->fresh()->thumbnail_path);
        Storage::disk('public')->assertExists($photo->fresh()->thumbnail_path);
    }

    public function test_duplicate_photos_for_the_same_user_are_skipped_with_a_message(): void
    {
        $file = UploadedFile::fake()->image('again.jpg', 100, 100);
        $this->actingAs($this->owner)->postJson('/photos', ['photos' => [$file]]);

        $response = $this->actingAs($this->owner)->postJson('/photos', ['photos' => [$file]]);

        $response->assertOk()
            ->assertJsonCount(0, 'uploaded')
            ->assertJsonCount(1, 'duplicates')
            ->assertJsonPath('duplicates.0.error', 'This photo is already backed up.');

        $this->assertDatabaseCount('photos', 1);
    }

    public function test_the_same_photo_is_allowed_for_a_different_user(): void
    {
        $file = UploadedFile::fake()->image('shared.jpg', 100, 100);

        $this->actingAs($this->owner)->postJson('/photos', ['photos' => [$file]]);
        $this->actingAs($this->stranger)
            ->postJson('/photos', ['photos' => [$file]])
            ->assertCreated();

        $this->assertDatabaseCount('photos', 2);
    }

    public function test_a_batch_of_duplicates_and_new_photos_uploads_the_new_ones(): void
    {
        $duplicate = UploadedFile::fake()->image('dup.jpg', 100, 100);
        $this->actingAs($this->owner)->postJson('/photos', ['photos' => [$duplicate]]);

        $fresh = UploadedFile::fake()->image('fresh.jpg', 100, 101);

        $response = $this->actingAs($this->owner)->postJson('/photos', [
            'photos' => [$duplicate, $fresh],
            'keys' => ['d', 'f'],
        ]);

        $response->assertCreated()
            ->assertJsonCount(1, 'uploaded')
            ->assertJsonCount(1, 'duplicates')
            ->assertJsonPath('duplicates.0.key', 'd')
            ->assertJsonPath('uploaded.0.key', 'f');

        $this->assertDatabaseCount('photos', 2);
    }

    public function test_selected_photos_download_as_a_zip(): void
    {
        $one = $this->uploadFor($this->owner, 'one.jpg', 64, 64);
        $two = $this->uploadFor($this->owner, 'two.jpg', 65, 64);
        $three = $this->uploadFor($this->owner, 'three.jpg', 66, 64);

        $response = $this->actingAs($this->owner)->post('/photos/download-selected', [
            'ids' => [$one->id, $two->id, $three->id],
        ]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/zip');
    }

    public function test_download_selected_only_includes_the_owners_photos(): void
    {
        $mine = $this->uploadFor($this->owner, 'mine.jpg', 64, 64);
        $theirs = $this->uploadFor($this->stranger, 'theirs.jpg', 64, 65);

        $response = $this->actingAs($this->owner)->post('/photos/download-selected', [
            'ids' => [$mine->id, $theirs->id],
        ]);

        $response->assertOk();
        $names = $this->zipEntryNames($response);
        $this->assertContains('mine.jpg', $names);
        $this->assertNotContains('theirs.jpg', $names);
    }

    public function test_download_selected_rejects_more_than_200_photos(): void
    {
        $ids = [];
        for ($i = 1; $i <= 201; $i++) {
            $ids[] = $this->uploadFor($this->owner, "batch-$i.jpg", $i, 240)->id;
        }

        $this->from('/dashboard')
            ->post('/photos/download-selected', ['ids' => $ids])
            ->assertSessionHasErrors('download')
            ->assertRedirect('/dashboard');
    }

    public function test_download_selected_requires_at_least_one_photo(): void
    {
        $this->from('/dashboard')
            ->actingAs($this->owner)
            ->post('/photos/download-selected', ['ids' => []])
            ->assertSessionHasErrors('download')
            ->assertRedirect('/dashboard');
    }

    private function zipEntryNames(TestResponse $response): array
    {
        $zip = new \ZipArchive;
        $zip->open($this->zipTmpPath($response));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        return $names;
    }

    private function zipTmpPath(TestResponse $response): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'zipcheck');
        file_put_contents($tmp, file_get_contents($response->baseResponse->getFile()->getPathname()));

        return $tmp;
    }

    public function test_the_storage_warning_banner_appears_near_the_quota_limit(): void
    {
        $user = User::factory()->create(['storage_limit_mb' => 10]);
        $photo = new Photo([
            'user_id' => $user->id,
            'file_path' => 'photos/big.jpg',
            'original_filename' => 'big.jpg',
            'size_bytes' => (int) (9 * 1024 * 1024),
            'mime_type' => 'image/jpeg',
        ]);
        $photo->save();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertSee('Storage nearly full')
            ->assertSee('90% used');
    }

    public function test_the_storage_warning_banner_is_hidden_while_quota_is_healthy(): void
    {
        $user = User::factory()->create(['storage_limit_mb' => 10]);
        $photo = new Photo([
            'user_id' => $user->id,
            'file_path' => 'photos/small.jpg',
            'original_filename' => 'small.jpg',
            'size_bytes' => 1024 * 1024,
            'mime_type' => 'image/jpeg',
        ]);
        $photo->save();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertDontSee('Storage nearly full');
    }

    public function test_uploads_are_rejected_with_a_clear_message_when_the_quota_is_full(): void
    {
        $user = User::factory()->create(['storage_limit_mb' => 1]);
        $photo = new Photo([
            'user_id' => $user->id,
            'file_path' => 'photos/base.jpg',
            'original_filename' => 'base.jpg',
            'size_bytes' => 1024 * 1024,
            'mime_type' => 'image/jpeg',
        ]);
        $photo->save();

        $file = UploadedFile::fake()->image('new.jpg', 64, 64);

        $response = $this->actingAs($user)->postJson('/photos', [
            'photos' => [$file],
            'keys' => ['n'],
        ]);

        $response->assertOk()
            ->assertJsonCount(0, 'uploaded')
            ->assertJsonCount(1, 'failed')
            ->assertJsonPath('failed.0.error', 'Storage quota is full; free up space before uploading more photos.');
    }

    public function test_photo_detail_shows_previous_and_next_navigation_arrows(): void
    {
        $older = $this->uploadFor($this->owner, 'a.jpg', 240, 200);
        $current = $this->uploadFor($this->owner, 'b.jpg', 320, 240);
        $newer = $this->uploadFor($this->owner, 'c.jpg', 480, 360);

        $response = $this->actingAs($this->owner)->get('/photos/'.$current->id);

        $response->assertOk()
            ->assertSee('Previous photo', false)
            ->assertSee('Next photo', false)
            ->assertSee(route('photos.show', $newer), false)
            ->assertSee(route('photos.show', $older), false);
    }

    public function test_photo_detail_hides_arrows_when_there_are_no_neighbouring_photos(): void
    {
        $photo = $this->uploadFor($this->owner);

        $response = $this->actingAs($this->owner)->get('/photos/'.$photo->id);

        $response->assertOk()
            ->assertDontSee('Previous photo', false)
            ->assertDontSee('Next photo', false);
    }

    public function test_bulk_edit_updates_the_selected_photos(): void
    {
        $one = $this->uploadFor($this->owner, 'one.jpg', 320, 240);
        $two = $this->uploadFor($this->owner, 'two.jpg', 321, 240);
        $album = Album::create(['user_id' => $this->owner->id, 'name' => 'Summer']);

        $this->actingAs($this->owner)
            ->postJson('/photos/bulk-update', [
                'ids' => [$one->id, $two->id],
                'alt_text' => 'Beach day',
                'tags' => 'beach, sun',
                'album' => $album->id,
            ])
            ->assertOk()
            ->assertJson(['message' => 'Updated 2 photo(s).']);

        foreach ([$one, $two] as $photo) {
            $fresh = $photo->fresh();
            $this->assertSame('Beach day', $fresh->alt_text);
            $this->assertSame(['beach', 'sun'], $fresh->tags);
            $this->assertTrue($fresh->albums()->whereKey($album->id)->exists());
        }
    }

    public function test_bulk_edit_only_touches_the_users_own_photos(): void
    {
        $mine = $this->uploadFor($this->owner, 'mine.jpg', 320, 240);
        $theirs = $this->uploadFor($this->stranger, 'theirs.jpg', 321, 240);

        $this->actingAs($this->owner)
            ->postJson('/photos/bulk-update', [
                'ids' => [$mine->id, $theirs->id],
                'alt_text' => 'Mine only',
                'tags' => 'kept',
            ])
            ->assertOk()
            ->assertJson(['message' => 'Updated 1 photo(s).']);

        $this->assertSame('Mine only', $mine->fresh()->alt_text);
        $this->assertNull($theirs->fresh()->alt_text);
        $this->assertEmpty($theirs->fresh()->tags);
    }

    public function test_bulk_edit_requires_a_selection(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/photos/bulk-update', ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }

    public function test_a_user_can_rotate_their_photo(): void
    {
        $photo = $this->uploadFor($this->owner, 'rotate.jpg', 320, 240);
        $original = Storage::disk('public')->get($photo->file_path);

        $this->actingAs($this->owner)
            ->from('/photos/'.$photo->id)
            ->post('/photos/'.$photo->id.'/rotate', ['direction' => 'right'])
            ->assertRedirect('/photos/'.$photo->id)
            ->assertSessionHas('status', 'Photo rotated.');

        $fresh = $photo->fresh();
        $this->assertNotSame($original, Storage::disk('public')->get($fresh->file_path));
        $this->assertNotNull($fresh->thumbnail_path);
        Storage::disk('public')->assertExists($fresh->thumbnail_path);
    }

    public function test_rotating_a_photo_invalidates_its_cloud_backup(): void
    {
        config(['photobackup.cloud_disk' => null]);

        $photo = $this->uploadFor($this->owner, 'backedup.jpg', 320, 240);
        app(PhotoStorageService::class)->backupToCloud($photo);
        $this->assertNotNull($photo->fresh()->backed_up_at);

        $this->actingAs($this->owner)->post("/photos/{$photo->id}/rotate", ['direction' => 'left']);

        $this->assertNull($photo->fresh()->backed_up_at);
        $this->assertNull($photo->fresh()->remote_path);
    }

    public function test_a_user_cannot_rotate_another_users_photo(): void
    {
        $photo = $this->uploadFor($this->owner);

        $this->actingAs($this->stranger)
            ->post("/photos/{$photo->id}/rotate", ['direction' => 'left'])
            ->assertStatus(403);
    }

    public function test_latest_endpoint_returns_only_photos_newer_than_the_given_id(): void
    {
        $first = $this->uploadFor($this->owner, 'first.jpg', 320, 240);
        $second = $this->uploadFor($this->owner, 'second.jpg', 321, 240);

        $response = $this->actingAs($this->owner)
            ->getJson("/photos/latest?after={$first->id}")
            ->assertOk()
            ->assertJsonStructure(['photos' => [['id', 'url', 'thumbnail_url', 'description', 'taken_at']], 'latest_id']);

        $ids = collect($response->json('photos'))->pluck('id');

        $this->assertTrue($ids->contains($second->id));
        $this->assertFalse($ids->contains($first->id));
        $this->assertSame($second->id, $response->json('latest_id'));
    }

    public function test_latest_endpoint_only_returns_the_owners_photos(): void
    {
        $mine = $this->uploadFor($this->owner, 'mine.jpg');
        $theirs = $this->uploadFor($this->stranger, 'theirs.jpg');

        $ids = collect(
            $this->actingAs($this->owner)->getJson('/photos/latest?after=0')->assertOk()->json('photos')
        )->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_guests_cannot_use_the_latest_endpoint(): void
    {
        $this->getJson('/photos/latest')->assertStatus(401);
    }

    private function uploadFor(User $user, string $name = 'holiday-sunset.jpg', int $width = 320, int $height = 240): Photo
    {
        $file = UploadedFile::fake()->image($name, $width, $height);

        $this->actingAs($user)->postJson('/photos', ['photos' => [$file]]);

        return Photo::where('user_id', $user->id)->where('original_filename', $name)->firstOrFail();
    }
}
