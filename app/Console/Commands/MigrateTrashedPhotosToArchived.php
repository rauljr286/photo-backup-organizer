<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;

class MigrateTrashedPhotosToArchived extends Command
{
    protected $signature = 'photos:migrate-trashed-to-archived';

    protected $description = 'Migrate existing trashed photos (deleted_at) to archived state (archived_at)';

    public function handle(): int
    {
        $trashed = Photo::onlyTrashed()->get();
        $count = $trashed->count();

        if ($count === 0) {
            $this->info('No trashed photos to migrate.');

            return self::SUCCESS;
        }

        foreach ($trashed as $photo) {
            $photo->archived_at = $photo->deleted_at;
            $photo->deleted_at = null;
            $photo->saveQuietly();
        }

        $this->info($count.' trashed photo(s) migrated to archived state.');

        return self::SUCCESS;
    }
}
