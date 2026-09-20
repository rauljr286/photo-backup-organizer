<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Services\PhotoStorageService;
use Illuminate\Console\Command;
use Throwable;

class GenerateThumbnailsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'photos:generate-thumbnails';

    /**
     * @var string
     */
    protected $description = 'Backfill thumbnails for photos uploaded before thumbnail generation existed.';

    public function handle(PhotoStorageService $photos): int
    {
        $pending = Photo::query()->whereNull('thumbnail_path');

        if ($pending->count() === 0) {
            $this->info('No photos are missing thumbnails.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($pending->count());
        $bar->start();

        $generated = 0;
        $failed = 0;

        $pending->chunkById(100, function ($photosChunk) use ($photos, $bar, &$generated, &$failed) {
            foreach ($photosChunk as $photo) {
                try {
                    if ($photos->generateThumbnail($photo) !== null) {
                        $generated++;
                    } else {
                        $failed++;
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

        $summary = "Generated {$generated} thumbnail(s).";

        if ($failed > 0) {
            $summary .= " {$failed} photo(s) could not be processed; see the log for details.";
        }

        $this->info($summary);

        return self::SUCCESS;
    }
}
