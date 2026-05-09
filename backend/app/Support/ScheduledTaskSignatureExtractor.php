<?php

namespace App\Support;

/**
 * Phase D — extract bare artisan signature from a ScheduledTask `command` string.
 *
 * Laravel's scheduled-task event payload exposes the full command line that
 * will be executed (e.g. `'/usr/bin/php' 'artisan' linkedin:scan-blog --hours=24`).
 * The audit hook needs the bare signature (`linkedin:scan-blog`) to look up
 * the matching `scheduled_commands` row.
 *
 * Pure utility — no DB, no Laravel facades. Trivially testable.
 *
 * Examples:
 *   'php artisan linkedin:scan-blog --hours=24'         → 'linkedin:scan-blog'
 *   '/usr/bin/php artisan content:auto-pipeline'        → 'content:auto-pipeline'
 *   'D:\xampp\php\php.exe artisan inspire'              → 'inspire'
 *   'artisan blog:process-images'                       → 'blog:process-images'
 *   "'/usr/bin/php' 'artisan' linkedin:scan-blog"       → 'linkedin:scan-blog'  (Laravel Process-quoted form)
 *   'linkedin:scan-blog --hours=24'                     → 'linkedin:scan-blog'  (no php artisan prefix)
 *   'gibberish text'                                    → null
 *   ''                                                  → null
 */
class ScheduledTaskSignatureExtractor
{
    /**
     * Extract the bare signature (no args) from a command string.
     */
    public static function extract(string $command): ?string
    {
        $command = trim($command);

        if ($command === '') {
            return null;
        }

        // Tokenize on whitespace, then strip surrounding single/double quotes
        // off each token. Laravel's Process facade frequently emits
        // shell-quoted forms like:  '/usr/bin/php' 'artisan' linkedin:scan-blog
        $rawTokens = preg_split('/\s+/', $command);
        $tokens = [];
        foreach ($rawTokens as $token) {
            $unquoted = trim($token, "\"'");
            if ($unquoted !== '') {
                $tokens[] = $unquoted;
            }
        }

        if ($tokens === []) {
            return null;
        }

        // Find the position of the 'artisan' marker. Match either:
        //  - bare 'artisan' (e.g. `php artisan ...`)
        //  - any token whose basename === 'artisan' (handles
        //    `/var/www/Portfolio_v2/backend/artisan ...`)
        $artisanIndex = null;
        foreach ($tokens as $i => $token) {
            if ($token === 'artisan' || basename($token) === 'artisan') {
                $artisanIndex = $i;
                break;
            }
        }

        if ($artisanIndex !== null) {
            $next = $tokens[$artisanIndex + 1] ?? null;
            if ($next === null || $next === '') {
                return null;
            }
            return $next;
        }

        // No artisan marker — accept the first token only if it looks like a
        // bare signature pattern (`namespace:command` or single bare word).
        // Reject prose like "gibberish text" or "random words no colon".
        $first = $tokens[0];
        if (self::looksLikeSignature($first)) {
            return $first;
        }

        return null;
    }

    /**
     * A bare signature is `namespace:command` or a single Laravel-style
     * lowercase command identifier (no spaces, only alphanumerics +
     * `:` `-` `_`). Rejects multi-word prose.
     */
    private static function looksLikeSignature(string $token): bool
    {
        // namespace:command form (the common Laravel signature shape)
        if (preg_match('/^[a-z0-9][a-z0-9_-]*:[a-z0-9][a-z0-9:_-]*$/i', $token) === 1) {
            return true;
        }

        return false;
    }
}
