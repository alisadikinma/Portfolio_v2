<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateAboutExperience extends Command
{
    protected $signature = 'about:migrate-experience
        {--dry-run : Show what would change without writing}
        {--source= : Override source JSON path (default: docs/prod-deploy/experience-2026-05-05.json)}
        {--no-backup : Skip writing backup file (default: backup to storage/app/backups/)}';

    protected $description = 'One-shot: replace settings.about.experience with the canonical 12-entry JSON (5 INDUSIA-era + 7 historical from LinkedIn). Also fixes type=text rows in `about` group that should be `json` so the admin loader stops choking on string values. Idempotent.';

    private const JSON_TYPED_KEYS = [
        'skills',
        'experience',
        'social_links',
        'languages',
        'statistics',
        'certifications',
        'trust_strip',
        'what_i_do',
    ];

    public function handle(): int
    {
        $sourcePath = $this->option('source')
            ?: base_path('../docs/prod-deploy/experience-2026-05-05.json');

        if (!is_file($sourcePath)) {
            $this->error("Source JSON not found: {$sourcePath}");
            return self::FAILURE;
        }

        $rawJson = file_get_contents($sourcePath);
        $decoded = json_decode($rawJson, true);

        if (!is_array($decoded)) {
            $this->error('Source file is not valid JSON: ' . json_last_error_msg());
            return self::FAILURE;
        }

        $newCount = count($decoded);
        $this->info("Source JSON: {$newCount} experience entries from {$sourcePath}");

        $current = Setting::where('group', 'about')->where('key', 'experience')->first();
        $currentDecoded = $current ? json_decode((string) $current->value, true) : null;
        $currentCount = is_array($currentDecoded) ? count($currentDecoded) : 0;

        $this->info("Current DB: {$currentCount} experience entries (type={$current?->type})");

        if ($currentCount === $newCount && $current && $current->type === 'json' && $current->value === $rawJson) {
            $this->info('Already in sync — no changes needed.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->line('--- New entries (after migration) ---');
        foreach ($decoded as $i => $exp) {
            $title = $exp['title'] ?? '???';
            $company = $exp['company'] ?? '???';
            $start = $exp['start_date'] ?? '?';
            $end = ($exp['end_date'] ?? '') !== '' ? $exp['end_date'] : 'present';
            $this->line(sprintf('  %2d. %s @ %s (%s → %s)', $i + 1, $title, $company, $start, $end));
        }
        $this->line('');

        $typeFixes = $this->collectTypeFixes();
        if (!empty($typeFixes)) {
            $this->line('Type fixes pending: ' . implode(', ', $typeFixes));
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no writes performed.');
            return self::SUCCESS;
        }

        if (!$this->option('no-backup') && $current) {
            $this->writeBackup($current->value);
        }

        Setting::updateOrCreate(
            ['group' => 'about', 'key' => 'experience'],
            ['value' => $rawJson, 'type' => 'json']
        );

        if (!empty($typeFixes)) {
            Setting::where('group', 'about')
                ->whereIn('key', $typeFixes)
                ->update(['type' => 'json']);
            $this->info('Fixed type=json on: ' . implode(', ', $typeFixes));
        }

        $this->info("Done — settings.about.experience now has {$newCount} entries (type=json).");
        return self::SUCCESS;
    }

    private function collectTypeFixes(): array
    {
        return Setting::where('group', 'about')
            ->whereIn('key', self::JSON_TYPED_KEYS)
            ->where('type', '!=', 'json')
            ->pluck('key')
            ->all();
    }

    private function writeBackup(?string $oldValue): void
    {
        if ($oldValue === null) {
            return;
        }

        $stamp = now()->format('Ymd_His');
        $relative = "backups/about-experience-before-{$stamp}.json";

        Storage::disk('local')->put($relative, $oldValue);

        $this->info('Backup written: storage/app/' . $relative);
    }
}
