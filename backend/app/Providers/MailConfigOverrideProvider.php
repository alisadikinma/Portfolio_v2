<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use PDOException;

/**
 * Override Laravel mail.* config with values from the `settings` table
 * (group: 'mail') at boot. Lets the operator manage SMTP via the admin UI
 * without redeploying or editing .env.
 *
 * Failure modes are deliberately silent:
 *  - DB unavailable (artisan migrate:fresh on a clean schema, etc.)
 *  - settings table missing (pre-migration boot)
 *  - mail_password decryption fails (key rotation, corrupted value)
 *
 * In all of these the Laravel default config from .env / config/mail.php
 * stays in effect, so artisan + tests still boot.
 */
class MailConfigOverrideProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $rows = Setting::where('group', 'mail')->pluck('value', 'key')->toArray();
        } catch (QueryException | PDOException $e) {
            // Table missing or DB unreachable — nothing to override.
            return;
        }

        if (empty($rows)) {
            return;
        }

        $mailer = $rows['mail_mailer'] ?? null;

        // Only override SMTP-driver fields. If operator picks a different
        // mailer (log, array, etc.) the .env value still wins for that field.
        if ($mailer) {
            Config::set('mail.default', $mailer);
        }

        $smtp = [
            'host' => $rows['mail_host'] ?? null,
            'port' => isset($rows['mail_port']) ? (int) $rows['mail_port'] : null,
            'username' => $rows['mail_username'] ?? null,
            'encryption' => $rows['mail_encryption'] ?? null,
        ];

        foreach ($smtp as $key => $value) {
            if ($value !== null && $value !== '') {
                Config::set("mail.mailers.smtp.{$key}", $value);
            }
        }

        // Password is stored encrypted (Crypt::encryptString in controller).
        // Decrypt at boot — failures stay silent so a key-rotation incident
        // doesn't block artisan from booting.
        $encryptedPassword = $rows['mail_password'] ?? null;
        if (!empty($encryptedPassword)) {
            try {
                $plain = Crypt::decryptString($encryptedPassword);
                Config::set('mail.mailers.smtp.password', $plain);
            } catch (DecryptException $e) {
                Log::warning('Mail password decrypt failed — using .env fallback', [
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        $fromAddress = $rows['mail_from_address'] ?? null;
        $fromName = $rows['mail_from_name'] ?? null;

        if ($fromAddress) {
            Config::set('mail.from.address', $fromAddress);
        }
        if ($fromName) {
            Config::set('mail.from.name', $fromName);
        }
    }
}
