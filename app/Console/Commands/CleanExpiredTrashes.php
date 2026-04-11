<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Trash;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:clean-expired-trashes')]
#[Description('Process expired trash records — hard delete contacts and conversations')]
class CleanExpiredTrashes extends Command
{
    public function handle(): int
    {
        $count = 0;

        Trash::expired()->with('contact')->chunkById(100, function ($trashes) use (&$count) {
            foreach ($trashes as $trash) {
                DB::transaction(function () use ($trash) {
                    $contact = $trash->contact;

                    if ($contact) {
                        $conversation = Conversation::betweenUsers($contact->user_id, $contact->contact_user_id)->first();
                        if ($conversation) {
                            $conversation->messages()->delete();
                            $conversation->delete();
                        }

                        $contact->delete();
                    }

                    $trash->delete();
                });

                $count++;
            }
        });

        $this->info("Processed {$count} expired trash record(s).");

        return self::SUCCESS;
    }
}
