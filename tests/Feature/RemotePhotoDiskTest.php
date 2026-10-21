<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use App\Services\PhotoStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\RemoteFilesystemAdapter;
use Tests\TestCase;

/**
 * The photos live on a bucket that has no path on the server, the way they
 * have to live on a serverless platform: every file the application touches
 * is copied to a temporary file first.
 */
class RemotePhotoDiskTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        Storage::set('photos-remote', $this->remoteDisk());

        config(['photobackup.photo_disk' => 'photos-remote']);

        $this->owner = User::factory()->create();
    }

    /**
     * A disk with its own storage that behaves like a bucket: it has no path
     * on the server, so every file has to be copied to a temporary file
     * before it can be processed.
     */
    private function remoteDisk(): RemoteFilesystemAdapter
    {
        $root = storage_path('framework/testing/disks/photos-remote');

        File::deleteDirectory($root);

        $bucket = Storage::build(['driver' => 'local', 'root' => $root]);

        return new RemoteFilesystemAdapter(
            $bucket->getDriver(),
            $bucket->getAdapter(),
            $bucket->getConfig(),
        );
    }

    public function test_an_uploaded_photo_is_stored_on_the_remote_disk(): void
    {
        $file = UploadedFile::fake()->image('remote.jpg', 640, 480);

        $response = $this->actingAs($this->owner)->postJson('/photos', ['photos' => [$file]]);

        $response->assertCreated()->assertJsonCount(1, 'uploaded');

        $photo = Photo::where('user_id', $this->owner->id)->firstOrFail();

        Storage::disk('photos-remote')->assertExists($photo->file_path);
        Storage::disk('public')->assertMissing($photo->file_path);
    }

    public function test_an_uploaded_photo_is_served_through_the_app(): void
    {
        $photo = $this->uploadFor($this->owner, 'served.jpg');

        $response = $this->actingAs($this->owner)->get(route('photos.raw', $photo));

        $response->assertOk();
        $this->assertSame(
            Storage::disk('photos-remote')->get($photo->file_path),
            $response->streamedContent()
        );
    }

    public function test_a_stranger_cannot_read_a_photo_from_the_remote_disk(): void
    {
        $photo = $this->uploadFor($this->owner, 'private.jpg');

        $this->actingAs(User::factory()->create())
            ->get(route('photos.raw', $photo))
            ->assertStatus(403);
    }

    public function test_the_thumbnail_of_a_remote_photo_is_generated_on_the_remote_disk(): void
    {
        $photo = $this->uploadFor($this->owner, 'thumb.jpg', 640, 480);

        $this->assertNotNull($photo->fresh()->thumbnail_path);
        Storage::disk('photos-remote')->assertExists($photo->fresh()->thumbnail_path);

        $thumbnail = Storage::disk('photos-remote')->get($photo->fresh()->thumbnail_path);
        $size = getimagesizefromstring($thumbnail);

        $this->assertNotFalse($size, 'The thumbnail should be a readable image.');
        $this->assertSame(400, $size[0]);
        $this->assertSame(300, $size[1]);

        $this->actingAs($this->owner)
            ->get(route('photos.thumbnail', $photo))
            ->assertOk();
    }

    public function test_rotating_a_remote_photo_rewrites_the_file_on_the_remote_disk(): void
    {
        $photo = $this->uploadFor($this->owner, 'rotate.jpg', 320, 240);
        $before = Storage::disk('photos-remote')->get($photo->file_path);

        $this->actingAs($this->owner)
            ->from('/photos/'.$photo->id)
            ->post('/photos/'.$photo->id.'/rotate', ['direction' => 'right'])
            ->assertRedirect('/photos/'.$photo->id)
            ->assertSessionHas('status', 'Photo rotated.');

        $after = Storage::disk('photos-remote')->get($photo->fresh()->file_path);

        $this->assertNotSame($before, $after);

        $size = getimagesizefromstring($after);
        $this->assertSame(240, $size[0]);
        $this->assertSame(320, $size[1]);

        Storage::disk('photos-remote')->assertExists($photo->fresh()->thumbnail_path);
    }

    public function test_rotating_a_remote_photo_invalidates_its_cloud_backup(): void
    {
        config(['photobackup.cloud_disk' => null]);

        $photo = $this->uploadFor($this->owner, 'backedup.jpg', 320, 240);
        app(PhotoStorageService::class)->backupToCloud($photo);

        $this->assertNotNull($photo->fresh()->backed_up_at);

        $this->actingAs($this->owner)->post("/photos/{$photo->id}/rotate", ['direction' => 'left']);

        $this->assertNull($photo->fresh()->backed_up_at);
        $this->assertNull($photo->fresh()->remote_path);
    }

    public function test_the_selected_photos_zip_contains_the_original_files_from_the_remote_disk(): void
    {
        $one = $this->uploadFor($this->owner, 'one.jpg', 64, 64);
        $two = $this->uploadFor($this->owner, 'two.jpg', 65, 64);

        $response = $this->actingAs($this->owner)->post('/photos/download-selected', [
            'ids' => [$one->id, $two->id],
        ]);

        $response->assertOk()->assertHeader('Content-Type', 'application/zip');

        $names = $this->zipEntryNames($response);

        $this->assertContains('one.jpg', $names);
        $this->assertContains('two.jpg', $names);
        $this->assertSame(
            Storage::disk('photos-remote')->get($one->file_path),
            $this->zipEntryContent($response, 'one.jpg')
        );
    }

    public function test_the_album_zip_contains_the_original_files_from_the_remote_disk(): void
    {
        $photo = $this->uploadFor($this->owner, 'album.jpg', 64, 64);
        $album = $photo->albums()->create(['user_id' => $this->owner->id, 'name' => 'Trip']);

        $response = $this->actingAs($this->owner)->get(route('albums.download', $album));

        $response->assertOk();

        $this->assertContains('album.jpg', $this->zipEntryNames($response));
        $this->assertSame(
            Storage::disk('photos-remote')->get($photo->file_path),
            $this->zipEntryContent($response, 'album.jpg')
        );
    }

    public function test_the_hash_backfill_command_works_with_the_remote_disk(): void
    {
        $photo = $this->uploadFor($this->owner);
        $photo->update(['file_hash' => null]);

        $this->artisan('photos:backfill-hashes')->assertExitCode(0);

        $this->assertSame(
            hash('sha256', Storage::disk('photos-remote')->get($photo->file_path)),
            $photo->fresh()->file_hash
        );
    }

    public function test_the_backup_mirrors_a_remote_photo_to_the_cloud_disk(): void
    {
        config(['photobackup.cloud_disk' => null]);

        $photo = $this->uploadFor($this->owner);

        app(PhotoStorageService::class)->backupToCloud($photo);

        $this->assertNotNull($photo->fresh()->backed_up_at);
        Storage::disk('local')->assertExists($photo->fresh()->remote_path);
        $this->assertSame(
            Storage::disk('photos-remote')->get($photo->file_path),
            Storage::disk('local')->get($photo->fresh()->remote_path)
        );
    }

    public function test_an_avatar_is_stored_on_the_remote_disk(): void
    {
        $this->actingAs($this->owner)->post('/settings/avatar', [
            'avatar' => UploadedFile::fake()->image('me.png'),
        ])->assertSessionHasNoErrors();

        $this->owner->refresh();

        $this->assertNotNull($this->owner->avatar_path);
        Storage::disk('photos-remote')->assertExists($this->owner->avatar_path);

        $this->actingAs($this->owner)
            ->get(route('avatar', $this->owner))
            ->assertOk();
    }

    private function uploadFor(User $user, string $name = 'holiday-sunset.jpg', int $width = 320, int $height = 240): Photo
    {
        $file = UploadedFile::fake()->image($name, $width, $height);

        $this->actingAs($user)->postJson('/photos', ['photos' => [$file]]);

        return Photo::where('user_id', $user->id)->where('original_filename', $name)->firstOrFail();
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

    private function zipEntryContent(TestResponse $response, string $entry): string
    {
        $zip = new \ZipArchive;
        $zip->open($this->zipTmpPath($response));

        $content = (string) $zip->getFromName($entry);

        $zip->close();

        return $content;
    }

    private function zipTmpPath(TestResponse $response): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'zipcheck');
        file_put_contents($tmp, file_get_contents($response->baseResponse->getFile()->getPathname()));

        return $tmp;
    }
}
