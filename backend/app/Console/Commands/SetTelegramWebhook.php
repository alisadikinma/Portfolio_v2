<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * One-shot CLI to register (or unregister) the Telegram inbound webhook
 * with the bot. Reads telegram_bot_token + telegram_webhook_secret from
 * settings, builds the webhook URL from APP_URL, calls Telegram's
 * setWebhook (or deleteWebhook on --unset).
 *
 * Operator runs this once per environment after seeding telegram_webhook_secret:
 *   php artisan telegram:set-webhook
 *
 * Telegram will POST callback_query payloads to:
 *   {APP_URL}/api/automation/telegram/webhook
 * with the X-Telegram-Bot-Api-Secret-Token header set to the secret.
 */
class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {--unset : Delete the webhook instead of setting it}';

    protected $description = 'Register the Telegram inbound webhook with the bot (or delete it via --unset)';

    public function handle(): int
    {
        $token = (string) Setting::where('group', 'telegram')
            ->where('key', 'telegram_bot_token')
            ->value('value');
        if ($token === '') {
            $this->error('telegram_bot_token not set — configure via admin UI first.');
            return self::FAILURE;
        }

        $unset = (bool) $this->option('unset');

        if ($unset) {
            return $this->deleteWebhook($token);
        }

        $secret = (string) Setting::where('group', 'telegram')
            ->where('key', 'telegram_webhook_secret')
            ->value('value');
        if ($secret === '') {
            $this->error('telegram_webhook_secret not set — run TelegramSettingsSeeder.');
            return self::FAILURE;
        }

        $url = rtrim((string) config('app.url'), '/') . '/api/automation/telegram/webhook';

        try {
            $response = Http::timeout(15)->post(
                'https://api.telegram.org/bot' . $token . '/setWebhook',
                [
                    'url' => $url,
                    'secret_token' => $secret,
                    'allowed_updates' => ['callback_query'],
                ]
            );
        } catch (\Throwable $e) {
            $this->error('setWebhook HTTP call threw: ' . $e->getMessage());
            return self::FAILURE;
        }

        $body = $response->json();
        if (!$response->successful() || !($body['ok'] ?? false)) {
            $this->error('setWebhook failed: ' . json_encode($body));
            return self::FAILURE;
        }

        $this->info("✅ Webhook registered.");
        $this->line('   URL: ' . $url);
        $this->line('   Allowed updates: callback_query');
        return self::SUCCESS;
    }

    private function deleteWebhook(string $token): int
    {
        try {
            $response = Http::timeout(15)->post(
                'https://api.telegram.org/bot' . $token . '/deleteWebhook',
                ['drop_pending_updates' => false]
            );
        } catch (\Throwable $e) {
            $this->error('deleteWebhook HTTP call threw: ' . $e->getMessage());
            return self::FAILURE;
        }

        $body = $response->json();
        if (!$response->successful() || !($body['ok'] ?? false)) {
            $this->error('deleteWebhook failed: ' . json_encode($body));
            return self::FAILURE;
        }

        $this->info('✅ Webhook deleted.');
        return self::SUCCESS;
    }
}
