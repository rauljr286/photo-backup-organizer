<?php

namespace Tests\Feature;

use App\Contracts\DirectUploadSigner;
use App\Models\PendingUpload;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDirectUploadSigner;
use Tests\TestCase;

/**
 * The photos are uploaded straight to the bucket: the browser sends the file
 * to a signed URL and the server only registers it afterwards.
 */
class DirectUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private FakeDirectUploadSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('public');

        config([
            'photobackup.photo_disk' => 's3',
            'photobackup.direct_upload' => true,
        ]);

        $this->signer = new FakeDirectUploadSigner;
        $this->app->instance(DirectUploadSigner::class, $this->signer);

        $this->owner = User::factory()->create();
    }

    public function test_preparing_an_upload_hands_out_a_signed_url(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/photos/uploads/prepare', [
            'filename' => 'holiday.jpg',
            'size' => 2048,
            'mime_type' => 'image/jpeg',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'upload_id',
            'key',
            'url',
            'headers',
            'method',
            'expires_in',
        ]);

        $this->assertSame('PUT', $response->json('method'));
        $this->assertSame('image/jpeg', $response->json('headers.Content-Type'));
        $this->assertStringStartsWith("incoming/{$this->owner->id}/", $response->json('key'));
        $this->assertStringEndsWith('.jpg', $response->json('key'));
        $this->assertStringContainsString('incoming/', $response->json('url'));

        $this->assertDatabaseHas('pending_uploads', [
            'id' => $response->json('upload_id'),
            'user_id' => $this->owner->id,
            'status' => PendingUpload::STATUS_PENDING,
            'size_bytes' => 2048,
        ]);

        $this->assertCount(1, $this->signer->signed);
        $this->assertSame($response->json('key'), $this->signer->signed[0]['path']);
    }

    public function test_preparing_an_upload_refuses_an_unsupported_format(): void
    {
        $this->actingAs($this->owner)->postJson('/photos/uploads/prepare', [
            'filename' => 'notes.pdf',
            'size' => 2048,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Unsupported format: only JPG, PNG, WebP and GIF images are allowed.');

        $this->assertDatabaseCount('pending_uploads', 0);
    }

    public function test_preparing_an_upload_refuses_a_file_that_is_too_large(): void
    {
        config(['photobackup.max_upload_mb' => 1]);

        $this->actingAs($this->owner)->postJson('/photos/uploads/prepare', [
            'filename' => 'huge.jpg',
            'size' => 2 * 1024 * 1024,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'File too large: max 1MB per photo.');
    }

    public function test_preparing_an_upload_refuses_an_album_of_another_user(): void
    {
        $stranger = User::factory()->create();
        $album = $stranger->albums()->create(['name' => 'Not yours']);

        $this->actingAs($this->owner)->postJson('/photos/uploads/prepare', [
            'filename' => 'holiday.jpg',
            'size' => 2048,
            'album_id' => $album->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'The album could not be found.');
    }

    public function test_preparing_an_upload_refuses_a_file_beyond_the_quota(): void
    {
        $this->owner->update(['storage_limit_mb' => 1]);

        $this->actingAs($this->owner)->postJson('/photos/uploads/prepare', [
            'filename' => 'holiday.jpg',
            'size' => 2 * 1024 * 1024,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Storage quota is full; free up space before uploading more photos.');
    }

    public function test_the_quota_counts_the_uploads_that_are_still_on_their_way(): void
    {
        $this->owner->update(['storage_limit_mb' => 1]);

        $this->prepare('one.jpg', 600 * 1024);

        $this->actingAs($this->owner)->postJson('/photos/uploads/prepare', [
            'filename' => 'two.jpg',
            'size' => 600 * 1024,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Storage quota is full; free up space before uploading more photos.');
    }

    public function test_guests_cannot_prepare_an_upload(): void
    {
        $this->postJson('/photos/uploads/prepare', [
            'filename' => 'holiday.jpg',
            'size' => 2048,
        ])->assertUnauthorized();
    }

    public function test_completing_an_upload_registers_the_photo(): void
    {
        $prepared = $this->prepare('holiday.jpg');

        Storage::disk('s3')->put($prepared['key'], $this->imageBytes());

        $response = $this->actingAs($this->owner)->postJson('/photos/uploads/complete', [
            'upload_id' => $prepared['upload_id'],
        ]);

        $response->assertCreated()->assertJsonCount(1, 'uploaded');

        $photo = Photo::where('user_id', $this->owner->id)->firstOrFail();

        $this->assertStringStartsWith("photos/{$this->owner->id}/", $photo->file_path);
        $this->assertStringEndsWith('.jpg', $photo->file_path);
        $this->assertStringNotContainsString('holiday', $photo->file_path);
        $this->assertSame('holiday.jpg', $photo->original_filename);
        $this->assertFalse($photo->is_private);
        $this->assertNotEmpty($photo->file_hash);

        Storage::disk('s3')->assertExists($photo->file_path);
        Storage::disk('s3')->assertMissing($prepared['key']);

        $this->assertNotNull($photo->thumbnail_path);
        Storage::disk('s3')->assertExists($photo->thumbnail_path);

        $this->assertSame(
            PendingUpload::STATUS_COMPLETED,
            PendingUpload::findOrFail($prepared['upload_id'])->status
        );
    }

    public function test_completing_an_upload_attaches_the_photo_to_the_album(): void
    {
        $album = $this->owner->albums()->create(['name' => 'Trip']);

        $prepared = $this->prepare('holiday.jpg', 2048, ['album_id' => $album->id]);

        Storage::disk('s3')->put($prepared['key'], $this->imageBytes());

        $this->actingAs($this->owner)->postJson('/photos/uploads/complete', [
            'upload_id' => $prepared['upload_id'],
        ])->assertCreated();

        $photo = Photo::where('user_id', $this->owner->id)->firstOrFail();

        $this->assertTrue($photo->albums->contains($album->id));
    }

    public function test_a_private_upload_lands_in_the_private_gallery(): void
    {
        $this->owner->update(['private_password' => 'secret']);

        $prepared = $this->prepare('hidden.jpg', 2048, ['private' => true], true);

        Storage::disk('s3')->put($prepared['key'], $this->imageBytes());

        $this->actingAs($this->owner)
            ->withSession(['private_unlocked' => true])
            ->postJson('/photos/uploads/complete', ['upload_id' => $prepared['upload_id']])
            ->assertCreated();

        $photo = Photo::where('user_id', $this->owner->id)->firstOrFail();

        $this->assertTrue($photo->is_private);
    }

    public function test_a_private_upload_needs_the_gallery_to_be_unlocked(): void
    {
        $this->owner->update(['private_password' => 'secret']);

        $this->actingAs($this->owner)->postJson('/photos/uploads/prepare', [
            'filename' => 'hidden.jpg',
            'size' => 2048,
            'private' => true,
        ])->assertStatus(403);

        $this->assertDatabaseCount('pending_uploads', 0);
    }

    public function test_completing_an_upload_that_was_never_received_is_refused(): void
    {
        $prepared = $this->prepare('holiday.jpg');

        $this->actingAs($this->owner)->postJson('/photos/uploads/complete', [
            'upload_id' => $prepared['upload_id'],
        ])->assertStatus(422)
            ->assertJsonPath('message', 'The photo was not received. Please upload it again.');

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_completing_an_upload_of_another_user_is_refused(): void
    {
        $prepared = $this->prepare('holiday.jpg');

        $this->actingAs(User::factory()->create())->postJson('/photos/uploads/complete', [
            'upload_id' => $prepared['upload_id'],
        ])->assertStatus(404);

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_completing_an_expired_upload_is_refused(): void
    {
        $prepared = $this->prepare('holiday.jpg');

        Storage::disk('s3')->put($prepared['key'], $this->imageBytes());

        PendingUpload::whereKey($prepared['upload_id'])->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($this->owner)->postJson('/photos/uploads/complete', [
            'upload_id' => $prepared['upload_id'],
        ])->assertStatus(410);

        $this->assertDatabaseCount('photos', 0);
        Storage::disk('s3')->assertMissing($prepared['key']);
    }

    public function test_completing_an_upload_with_a_file_that_is_not_an_image_is_refused(): void
    {
        $prepared = $this->prepare('broken.jpg');

        Storage::disk('s3')->put($prepared['key'], 'this is not an image');

        $response = $this->actingAs($this->owner)->postJson('/photos/uploads/complete', [
            'upload_id' => $prepared['upload_id'],
        ]);

        $response->assertOk()->assertJsonCount(0, 'uploaded')->assertJsonCount(1, 'failed');

        $this->assertStringContainsString(
            'corrupted',
            strtolower((string) $response->json('failed.0.error'))
        );

        $this->assertDatabaseCount('photos', 0);
        Storage::disk('s3')->assertMissing($prepared['key']);
    }

    public function test_completing_an_upload_with_a_file_that_is_too_large_is_refused(): void
    {
        config(['photobackup.max_upload_mb' => 1]);

        $prepared = $this->prepare('big.jpg', 100);

        // The browser announced a small file, but the object in the bucket is
        // bigger than the maximum.
        Storage::disk('s3')->put($prepared['key'], $this->imageBytes().str_repeat('x', 2 * 1024 * 1024));

        $response = $this->actingAs($this->owner)->postJson('/photos/uploads/complete', [
            'upload_id' => $prepared['upload_id'],
        ]);

        $response->assertOk()->assertJsonCount(1, 'failed');
        $this->assertStringContainsString('too large', strtolower((string) $response->json('failed.0.error')));

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_completing_an_upload_beyond_the_quota_is_refused(): void
    {
        $prepared = $this->prepare('holiday.jpg');

        Storage::disk('s3')->put($prepared['key'], $this->imageBytes());

        $this->owner->update(['storage_limit_mb' => 0]);

        $response = $this->actingAs($this->owner)->postJson('/photos/uploads/complete', [
            'upload_id' => $prepared['upload_id'],
        ]);

        $response->assertOk()->assertJsonCount(1, 'failed');
        $this->assertSame(
            'Storage quota is full; free up space before uploading more photos.',
            $response->json('failed.0.error')
        );

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_a_photo_that_is_already_backed_up_is_reported_as_a_duplicate(): void
    {
        $bytes = $this->imageBytes('same.jpg');

        $first = $this->prepare('same.jpg');
        Storage::disk('s3')->put($first['key'], $bytes);

        $this->actingAs($this->owner)->postJson('/photos/uploads/complete', [
            'upload_id' => $first['upload_id'],
        ])->assertCreated();

        $second = $this->prepare('same.jpg');
        Storage::disk('s3')->put($second['key'], $bytes);

        $response = $this->actingAs($this->owner)->postJson('/photos/uploads/complete', [
            'upload_id' => $second['upload_id'],
        ]);

        $response->assertOk()
            ->assertJsonCount(0, 'uploaded')
            ->assertJsonCount(1, 'duplicates')
            ->assertJsonPath('duplicates.0.error', 'This photo is already backed up.');

        $this->assertDatabaseCount('photos', 1);
    }

    public function test_the_photo_configuration_keeps_all_its_settings(): void
    {
        // Every setting must survive: a missing key would silently fall back
        // to a default somewhere else in the code.
        $this->assertNotNull(config('photobackup.max_upload_mb'));
        $this->assertGreaterThan(0, (int) config('photobackup.max_upload_mb'));
        $this->assertIsBool(config('photobackup.direct_upload'));
        $this->assertIsInt(config('photobackup.direct_upload_url_ttl'));
        $this->assertIsInt(config('photobackup.pending_ttl_minutes'));
    }

    public function test_the_api_can_prepare_and_complete_an_upload(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')->postJson('/api/photos/uploads/prepare', [
            'filename' => 'holiday.jpg',
            'size' => 2048,
            'mime_type' => 'image/jpeg',
        ]);

        $response->assertCreated();

        $prepared = $response->json();

        Storage::disk('s3')->put($prepared['key'], $this->imageBytes());

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/photos/uploads/complete', [
            'upload_id' => $prepared['upload_id'],
        ])->assertCreated()->assertJsonCount(1, 'uploaded');

        $this->assertDatabaseCount('photos', 1);
    }

    public function test_the_api_refuses_a_private_upload(): void
    {
        $this->owner->update(['private_password' => 'secret']);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/photos/uploads/prepare', [
            'filename' => 'hidden.jpg',
            'size' => 2048,
            'private' => true,
        ])->assertForbidden();

        $this->assertDatabaseCount('pending_uploads', 0);
    }

    public function test_the_direct_upload_endpoints_are_missing_when_the_feature_is_off(): void
    {
        config(['photobackup.direct_upload' => false]);

        $this->actingAs($this->owner)->postJson('/photos/uploads/prepare', [
            'filename' => 'holiday.jpg',
            'size' => 2048,
        ])->assertNotFound();

        $this->actingAs($this->owner)->postJson('/photos/uploads/complete', [
            'upload_id' => 'whatever',
        ])->assertNotFound();
    }

    public function test_the_classic_upload_still_works_when_the_feature_is_off(): void
    {
        config(['photobackup.direct_upload' => false]);

        $file = UploadedFile::fake()->image('classic.jpg', 64, 64);

        $this->actingAs($this->owner)->postJson('/photos', [
            'photos' => [$file],
        ])->assertCreated()->assertJsonCount(1, 'uploaded');

        $this->assertDatabaseCount('photos', 1);
    }

    public function test_the_dropzone_offers_the_direct_upload_only_when_it_is_enabled(): void
    {
        $this->actingAs($this->owner)->get('/dashboard')->assertSee('data-direct-upload="1"', false);

        config(['photobackup.direct_upload' => false]);

        $this->actingAs($this->owner)->get('/dashboard')->assertSee('data-direct-upload="0"', false);
    }

    public function test_the_expired_uploads_are_pruned(): void
    {
        $prepared = $this->prepare('holiday.jpg');

        Storage::disk('s3')->put($prepared['key'], $this->imageBytes());

        PendingUpload::whereKey($prepared['upload_id'])->update(['expires_at' => now()->subMinute()]);

        $this->artisan('photos:prune-pending-uploads')->assertExitCode(0);

        $this->assertDatabaseCount('pending_uploads', 0);
        Storage::disk('s3')->assertMissing($prepared['key']);
    }

    /**
     * Prepare an upload and return the answer of the server.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function prepare(
        string $filename = 'holiday.jpg',
        int $size = 2048,
        array $overrides = [],
        bool $unlockPrivateGallery = false,
    ): array {
        $request = $this->actingAs($this->owner);

        if ($unlockPrivateGallery) {
            $request = $request->withSession(['private_unlocked' => true]);
        }

        $response = $request->postJson('/photos/uploads/prepare', array_merge([
            'filename' => $filename,
            'size' => $size,
            'mime_type' => 'image/jpeg',
        ], $overrides));

        $response->assertCreated();

        return $response->json();
    }

    /**
     * The bytes of a real image, as the browser would send them.
     */
    private function imageBytes(string $name = 'holiday.jpg'): string
    {
        $file = UploadedFile::fake()->image($name, 120, 90);

        return (string) file_get_contents($file->getPathname());
    }
}
