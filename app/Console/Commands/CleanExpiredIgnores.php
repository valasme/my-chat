<?php

namespace App\Console\Commands;

use App\Models\Ignore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:clean-expired-ignores')]
#[Description('Delete expired ignore records')]
class CleanExpiredIgnores extends Command
{
    public function handle(): int
    {
        $count = Ignore::expired()->delete();

        $this->info("Deleted {$count} expired ignore(s).");

        return self::SUCCESS;
    }
}
