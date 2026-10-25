<?php

namespace App\Console\Commands;

use App\Services\DirectUploadService;
use Illuminate\Console\Command;

/**
 * Removes the direct uploads that were never finished: the temporary objects
 * in the bucket and the rows that belong to them.
 *
 * The bucket can clean up its own incoming/ folder as well; this command
 * makes sure the bookkeeping in the database does not grow forever.
 */
class PrunePendingUploadsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'photos:prune-pending-uploads';

    /**
     * @var string
     */
    protected $description = 'Remove expired direct uploads: the temporary objects in the bucket and their rows.';

    public function handle(DirectUploadService $uploads): int
    {
        $removed = $uploads->pruneExpired();

        $this->info("Removed {$removed} expired upload(s).");

        return self::SUCCESS;
    }
}
