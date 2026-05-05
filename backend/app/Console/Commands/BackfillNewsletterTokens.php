<?php

namespace App\Console\Commands;

use App\Models\Newsletter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillNewsletterTokens extends Command
{
    protected $signature = 'newsletter:backfill-tokens {--dry-run : Report without writing}';

    protected $description = 'One-shot: generate unsubscribe_token for any legacy newsletter rows missing one (idempotent)';

    public function handle(): int
    {
        $missing = Newsletter::whereNull('unsubscribe_token')->count();

        if ($missing === 0) {
            $this->info('All newsletter rows already have unsubscribe tokens.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: would generate tokens for {$missing} rows.");
            return self::SUCCESS;
        }

        $updated = 0;
        Newsletter::whereNull('unsubscribe_token')->chunkById(500, function ($subs) use (&$updated) {
            foreach ($subs as $sub) {
                $sub->update(['unsubscribe_token' => Str::random(32)]);
                $updated++;
            }
        });

        $this->info("Backfilled tokens for {$updated} newsletter rows.");
        return self::SUCCESS;
    }
}
