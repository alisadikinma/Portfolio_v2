<?php

namespace App\Providers;

use App\Models\ScheduledCommand;
use App\Support\ScheduledTaskSignatureExtractor;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fix asset URL - use config() instead of env() for cached configs
        $appUrl = config('app.url', 'http://localhost');
        URL::forceRootUrl($appUrl);

        // Force HTTPS in production
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        $this->registerScheduledCommandAuditHooks();
    }

    /**
     * Phase D — wire Laravel ScheduledTask* events to the
     * `scheduled_commands` audit columns. Listeners no-op silently
     * for any artisan command that isn't tracked in the table
     * (e.g. ad-hoc `php artisan migrate` runs).
     *
     * Each listener is wrapped in try/catch — the audit hook MUST
     * NEVER crash the schedule itself.
     */
    protected function registerScheduledCommandAuditHooks(): void
    {
        Event::listen(ScheduledTaskStarting::class, function (ScheduledTaskStarting $event) {
            try {
                $signature = ScheduledTaskSignatureExtractor::extract($event->task->command ?? '');
                if ($signature === null) {
                    return;
                }

                ScheduledCommand::where('signature', $signature)->update([
                    'last_run_at' => now(),
                    'last_status' => 'running',
                    'last_error' => null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ScheduledTaskStarting audit hook failed', [
                    'exception' => $e->getMessage(),
                    'command' => $event->task->command ?? null,
                ]);
            }
        });

        Event::listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $event) {
            try {
                $signature = ScheduledTaskSignatureExtractor::extract($event->task->command ?? '');
                if ($signature === null) {
                    return;
                }

                // Laravel hands us $event->runtime in SECONDS as a float.
                // Convert to integer milliseconds for the column.
                $runtimeSeconds = (float) ($event->runtime ?? 0);
                $durationMs = (int) round($runtimeSeconds * 1000);

                ScheduledCommand::where('signature', $signature)->update([
                    'last_finished_at' => now(),
                    'last_status' => 'success',
                    'last_duration_ms' => $durationMs,
                    'last_error' => null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ScheduledTaskFinished audit hook failed', [
                    'exception' => $e->getMessage(),
                    'command' => $event->task->command ?? null,
                ]);
            }
        });

        Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event) {
            try {
                $signature = ScheduledTaskSignatureExtractor::extract($event->task->command ?? '');
                if ($signature === null) {
                    return;
                }

                $errorMessage = $event->exception?->getMessage() ?? 'Unknown error';
                // TEXT column safety — truncate before write so an exotic
                // multi-MB exception payload can't blow up the row.
                $errorMessage = mb_substr($errorMessage, 0, 2000);

                ScheduledCommand::where('signature', $signature)->update([
                    'last_finished_at' => now(),
                    'last_status' => 'failed',
                    'last_error' => $errorMessage,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ScheduledTaskFailed audit hook failed', [
                    'exception' => $e->getMessage(),
                    'command' => $event->task->command ?? null,
                ]);
            }
        });
    }
}
