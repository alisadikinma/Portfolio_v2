<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Legacy command — delegates to social:publish-slot atomic orchestrator
 * (May 12, 2026 P5 ship).
 *
 * Kept as a thin alias so DB-driven scheduled_commands rows from prior
 * deploys (signature='linkedin:process-scheduled') continue to work without
 * a manual cleanup. P7 will remove the old DB row entirely and delete this
 * class.
 */
class ProcessScheduledLinkedInPosts extends Command
{
    protected $signature = 'linkedin:process-scheduled';
    protected $description = '[DEPRECATED] Delegates to social:publish-slot. Will be removed in P7.';

    public function handle(): int
    {
        $this->warn('[linkedin:process-scheduled] DEPRECATED — delegating to social:publish-slot');
        return Artisan::call('social:publish-slot');
    }
}
