<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds the `postiz` settings group — local-node cross-post pull model.
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md (Phase G).
 *
 * firstOrCreate (not updateOrCreate) so deploy re-runs never clobber
 * admin-edited values — same pattern as LinkedInSettingsSeeder / PublerSettingsSeeder.
 *
 * Default-OFF: postiz_enabled='false' → zero production impact until the
 * operator flips it (instant Publer revert by flipping back).
 */
class PostizSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Master switch — when 'false', every cross-post sibling keeps
            // dispatching to Publer exactly as before.
            ['key' => 'postiz_enabled', 'value' => 'false', 'type' => 'text'],
            // Job lease (minutes): claim window ≥ worst-case publish time
            // (IG video child-poll ~4 min → 10m safe).
            ['key' => 'postiz_lease_minutes', 'value' => '10', 'type' => 'text'],
            // Watchdog deadline (minutes after slot_due) before Publer fallback.
            ['key' => 'postiz_fallback_deadline_minutes', 'value' => '6', 'type' => 'text'],
            // Postiz API base URL (set per deploy — e.g. https://postiz.alisadikinma.com).
            ['key' => 'postiz_api_base_url', 'value' => null, 'type' => 'text'],
            // Stuck-job alert threshold (minutes) for Postiz-only jobs (IG video / Medium).
            ['key' => 'postiz_worker_alert_minutes', 'value' => '20', 'type' => 'text'],
        ];

        foreach ($settings as $row) {
            Setting::firstOrCreate(
                ['group' => 'postiz', 'key' => $row['key']],
                ['value' => $row['value'], 'type' => $row['type']]
            );
        }
    }
}
