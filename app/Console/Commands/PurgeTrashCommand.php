<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Services\PhotoStorageService;
use Illuminate\Console\Command;

class PurgeTrashCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'photos:purge-trash {--days= : Override the configured retention window}';

    /**
     * The console command description.
     */
    protected $description = 'Permanently delete photos that have been in the trash past the retention window';

    public function handle(PhotoStorageService $photos): int
    {
        $days = (int) ($this->option('days') ?: config('photobackup.trash_retention_days', 30));
        $cutoff = now()->subDays($days);

        $expired = Photo::onlyTrashed()->where('deleted_at', '<', $cutoff)->get();

        if ($expired->isEmpty()) {
            $this->info('No trashed photos have exceeded the retention window.');

            return self::SUCCESS;
        }

        foreach ($expired as $photo) {
            $photos->purge($photo);
        }

        $this->info($expired->count().' expired photo(s) permanently deleted.');

        return self::SUCCESS;
    }
}
