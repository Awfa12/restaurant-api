<?php

namespace App\Console\Commands;

use App\Models\Upload;
use App\Support\ImageReferences;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes uploads that no record uses once they are old enough (API-CONTRACT
 * §4 Images: 24 hours). Recent ones are kept: the owner may be filling the
 * form that will use them. Scheduled daily (routes/console.php).
 */
#[Signature('uploads:prune {--hours=24 : Keep uploads younger than this}')]
#[Description('Delete uploaded images that no record uses, older than --hours')]
class PruneUploadsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $usedPaths = ImageReferences::paths();

        $unused = Upload::query()
            ->where('created_at', '<', now()->subHours((int) $this->option('hours')))
            ->whereNotIn('path', $usedPaths)
            ->get();

        foreach ($unused as $upload) {
            Storage::disk('public')->delete($upload->path);
            $upload->delete();
        }

        $this->info("Deleted {$unused->count()} unused upload(s).");

        return self::SUCCESS;
    }
}
