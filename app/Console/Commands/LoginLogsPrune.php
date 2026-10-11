<?php

namespace App\Console\Commands;

use App\Models\LoginLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('login-logs:prune {--days=180 : Number of days to keep}')]
#[Description('Delete login log entries older than the specified number of days')]
class LoginLogsPrune extends Command
{
    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);
        $deleted = LoginLog::where('created_at', '<', $cutoff)->delete();
        if ($deleted > 0) {
            $this->info("Deleted {$deleted} login log entries older than {$days} days.");
        }

        return self::SUCCESS;
    }
}
