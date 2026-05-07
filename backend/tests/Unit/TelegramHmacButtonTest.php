<?php

namespace Tests\Unit;

use App\Services\TelegramNotificationService;
use Tests\TestCase;

/**
 * Phase F verification: HMAC sign + verify roundtrip for Telegram inline-button
 * callback_data. The HMAC isn't a security boundary by itself (the bot's
 * secret_token header gates inbound requests at the network layer); the HMAC
 * just proves the button text was issued by us, not pasted in by another
 * chat member who happens to know the chat exists.
 */
class TelegramHmacButtonTest extends TestCase
{
    private const SECRET = 'a-secret-shared-with-telegram-bot-32-chars';

    public function test_sign_and_verify_roundtrip(): void
    {
        $signed = TelegramNotificationService::signCallback('retry', 'linkedin', 42, self::SECRET);
        $verified = TelegramNotificationService::verifyCallback($signed, self::SECRET);

        $this->assertNotNull($verified);
        $this->assertSame('retry', $verified['action']);
        $this->assertSame('linkedin', $verified['kind']);
        $this->assertSame(42, $verified['id']);
    }

    public function test_signed_callback_fits_in_telegram_64_byte_limit(): void
    {
        $signed = TelegramNotificationService::signCallback('cancel', 'linkedin', 999999, self::SECRET);
        $this->assertLessThanOrEqual(
            64,
            strlen($signed),
            'callback_data must fit in Telegram 64-byte limit'
        );
    }

    public function test_tampered_hmac_rejected(): void
    {
        $signed = TelegramNotificationService::signCallback('retry', 'idea', 7, self::SECRET);
        // Flip the last char of the HMAC.
        $last = $signed[-1];
        $tampered = substr($signed, 0, -1) . ($last === 'a' ? 'b' : 'a');

        $this->assertNull(TelegramNotificationService::verifyCallback($tampered, self::SECRET));
    }

    public function test_tampered_action_rejected(): void
    {
        $signed = TelegramNotificationService::signCallback('retry', 'linkedin', 42, self::SECRET);
        // Replace 'retry' with 'cancel' — same HMAC but different action.
        $tampered = preg_replace('/^retry/', 'cancel', $signed);

        $this->assertNull(TelegramNotificationService::verifyCallback($tampered, self::SECRET));
    }

    public function test_tampered_id_rejected(): void
    {
        $signed = TelegramNotificationService::signCallback('cancel', 'linkedin', 42, self::SECRET);
        // Try to escalate from id=42 to id=43 keeping the original HMAC.
        $tampered = preg_replace('/:42:/', ':43:', $signed);

        $this->assertNull(TelegramNotificationService::verifyCallback($tampered, self::SECRET));
    }

    public function test_wrong_secret_rejected(): void
    {
        $signed = TelegramNotificationService::signCallback('retry', 'linkedin', 1, self::SECRET);
        $this->assertNull(TelegramNotificationService::verifyCallback($signed, 'different-secret'));
    }

    public function test_malformed_callback_rejected(): void
    {
        // Wrong number of segments
        $this->assertNull(TelegramNotificationService::verifyCallback('retry:linkedin:42', self::SECRET));
        $this->assertNull(TelegramNotificationService::verifyCallback('too:many:colons:here:five', self::SECRET));
        $this->assertNull(TelegramNotificationService::verifyCallback('', self::SECRET));
    }

    public function test_non_numeric_id_rejected(): void
    {
        // Manually craft a payload with non-numeric id; the HMAC would still
        // be computed over a valid-looking string, but verifyCallback rejects
        // before checking HMAC because the id segment must be ctype_digit.
        $bogus = 'retry:linkedin:notanumber:abc123def456';
        $this->assertNull(TelegramNotificationService::verifyCallback($bogus, self::SECRET));
    }

    public function test_empty_secret_rejected_during_verify(): void
    {
        $signed = TelegramNotificationService::signCallback('retry', 'linkedin', 1, self::SECRET);
        $this->assertNull(TelegramNotificationService::verifyCallback($signed, ''));
    }

    public function test_empty_secret_during_sign_emits_nosecret_sentinel(): void
    {
        // signCallback called before seeder runs — embed sentinel so
        // verifyCallback can return null cleanly instead of generating
        // a real HMAC against empty key.
        $signed = TelegramNotificationService::signCallback('retry', 'linkedin', 1, '');
        $this->assertStringEndsWith(':NOSECRET', $signed);
        $this->assertNull(TelegramNotificationService::verifyCallback($signed, self::SECRET));
    }
}
