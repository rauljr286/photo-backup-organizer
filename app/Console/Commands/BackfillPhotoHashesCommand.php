<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Services\PhotoStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackfillPhotoHashesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'photos:backfill-hashes';

    /**
     * @var string
     */
    protected $description = 'Backfill content hashes (used for duplicate detection) for photos uploaded before hashing existed.';

    public function handle(PhotoStorageService $photos): int
    {
        $pending = Photo::query()->whereNull('file_hash');

        if ($pending->count() === 0) {
            $this->info('No photos are missing content hashes.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($pending->count());
        $bar->start();

        $hashed = 0;
        $failed = 0;

        $pending->chunkById(100, function ($photosChunk) use ($photos, $bar, &$hashed, &$failed) {
            foreach ($photosChunk as $photo) {
                $absolutePath = Storage::disk('public')->path($photo->file_path);

                if (! file_exists($absolutePath)) {
                    $failed++;
                    $bar->advance();

                    continue;
                }

                try {
                    $hash = $photos->hashFile($absolutePath);

                    if ($hash === '') {
                        $failed++;
                    } else {
                        $photo->file_hash = $hash;
                        $photo->saveQuietly();
                        $hashed++;
                    }
                } catch (Throwable $e) {
                    report($e);
                    $failed++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $summary = "Hashed {$hashed} photo(s).";

        if ($failed > 0) {
            $summary .= " {$failed} photo(s) could not be hashed; see the log for details.";
        }

        $this->info($summary);

        return self::SUCCESS;
    }
}
