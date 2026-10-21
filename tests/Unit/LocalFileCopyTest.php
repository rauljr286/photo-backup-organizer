<?php

namespace Tests\Unit;

use App\Support\FileWrite;
use App\Support\LocalFileCopy;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RemoteFilesystemAdapter;
use Tests\TestCase;

class LocalFileCopyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // A bucket has no path on the server, so every file has to be copied
        // to a temporary file before it can be processed.
        $root = storage_path('framework/testing/disks/photos-remote');

        File::deleteDirectory($root);

        $bucket = Storage::build(['driver' => 'local', 'root' => $root]);

        Storage::set('photos-remote', new RemoteFilesystemAdapter(
            $bucket->getDriver(),
            $bucket->getAdapter(),
            $bucket->getConfig(),
        ));
    }

    public function test_a_local_disk_hands_its_own_path_to_the_callback(): void
    {
        Storage::disk('public')->put('photos/1/a.jpg', 'binary-content');

        $seen = [];

        LocalFileCopy::withLocalCopy('public', 'photos/1/a.jpg', function (string $localPath, bool $isTemporary) use (&$seen) {
            $seen = [$localPath, $isTemporary];

            return null;
        });

        $this->assertFalse($seen[1], 'A local disk must not use a temporary copy.');
        $this->assertFileExists($seen[0]);
        $this->assertSame('binary-content', file_get_contents($seen[0]));
    }

    public function test_a_remote_disk_hands_a_temporary_copy_to_the_callback(): void
    {
        Storage::disk('photos-remote')->put('photos/1/a.jpg', 'binary-content');

        $seen = [];
        $result = LocalFileCopy::withLocalCopy('photos-remote', 'photos/1/a.jpg', function (string $localPath, bool $isTemporary) use (&$seen) {
            $seen = [$localPath, $isTemporary];

            return file_get_contents($localPath);
        });

        $this->assertTrue($seen[1], 'A remote disk must use a temporary copy.');
        $this->assertNotSame('photos/1/a.jpg', $seen[0]);
        $this->assertSame('binary-content', $result);
    }

    public function test_the_temporary_copy_is_removed_afterwards(): void
    {
        Storage::disk('photos-remote')->put('photos/1/a.jpg', 'binary-content');

        $temporary = null;

        LocalFileCopy::withLocalCopy('photos-remote', 'photos/1/a.jpg', function (string $localPath) use (&$temporary) {
            $temporary = $localPath;

            return null;
        });

        $this->assertNotNull($temporary);
        $this->assertFileDoesNotExist($temporary);
    }

    public function test_the_temporary_copy_is_removed_when_the_callback_throws(): void
    {
        Storage::disk('photos-remote')->put('photos/1/a.jpg', 'binary-content');

        $temporary = null;

        try {
            LocalFileCopy::withLocalCopy('photos-remote', 'photos/1/a.jpg', function (string $localPath) use (&$temporary) {
                $temporary = $localPath;

                throw new \RuntimeException('boom');
            });

            $this->fail('The exception should have been rethrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertNotNull($temporary);
        $this->assertFileDoesNotExist($temporary);
    }

    public function test_a_result_written_back_by_the_callback_replaces_the_file_on_the_disk(): void
    {
        Storage::disk('photos-remote')->put('photos/1/a.jpg', 'binary-content');

        LocalFileCopy::withLocalCopy(
            'photos-remote',
            'photos/1/a.jpg',
            fn (string $localPath): FileWrite => FileWrite::of('rotated-content'),
        );

        $this->assertSame('rotated-content', Storage::disk('photos-remote')->get('photos/1/a.jpg'));
    }

    public function test_a_result_written_back_by_the_callback_replaces_the_file_on_a_local_disk(): void
    {
        Storage::disk('public')->put('photos/1/a.jpg', 'binary-content');

        LocalFileCopy::withLocalCopy(
            'public',
            'photos/1/a.jpg',
            fn (string $localPath): FileWrite => FileWrite::of('rotated-content'),
        );

        $this->assertSame('rotated-content', Storage::disk('public')->get('photos/1/a.jpg'));
    }

    public function test_a_missing_file_is_reported_as_a_missing_local_path(): void
    {
        $seen = [];

        LocalFileCopy::withLocalCopy('photos-remote', 'photos/1/missing.jpg', function (string $localPath, bool $isTemporary) use (&$seen) {
            $seen = [$localPath, $isTemporary];

            return null;
        });

        $this->assertTrue($seen[1]);
        $this->assertFileDoesNotExist($seen[0]);
    }
}
