<?php

namespace App\Console\Commands;

use App\Services\AutoPipelineOrchestrator;
use Illuminate\Console\Command;

class RunAutoPipeline extends Command
{
    protected $signature = 'content:auto-pipeline';

    protected $description = 'Advance auto_mode content ideas one stage per tick with strict sequential gating';

    public function handle(AutoPipelineOrchestrator $orchestrator): int
    {
        $idea = $orchestrator->tick();

        if ($idea === null) {
            $this->line('idle');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Advanced idea #%d — status=%s',
            $idea->id,
            $idea->fresh()->status ?? $idea->status
        ));

        return self::SUCCESS;
    }
}
