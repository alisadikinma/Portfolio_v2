<?php

namespace Tests\Unit;

use App\Support\ScheduledTaskSignatureExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Phase D — verifies the audit-hook signature extractor handles every
 * real-world `command` string Laravel can hand us through the
 * Scheduled* events.
 */
class ScheduledTaskSignatureExtractorTest extends TestCase
{
    public function test_extracts_signature_from_full_artisan_command(): void
    {
        $this->assertSame(
            'linkedin:scan-blog',
            ScheduledTaskSignatureExtractor::extract('php artisan linkedin:scan-blog --hours=24')
        );
    }

    public function test_extracts_signature_with_full_php_path(): void
    {
        $this->assertSame(
            'content:auto-pipeline',
            ScheduledTaskSignatureExtractor::extract('/usr/bin/php artisan content:auto-pipeline')
        );
    }

    public function test_extracts_bare_signature_with_args(): void
    {
        $this->assertSame(
            'linkedin:scan-blog',
            ScheduledTaskSignatureExtractor::extract('linkedin:scan-blog --hours=24')
        );
    }

    public function test_extracts_command_with_no_args(): void
    {
        $this->assertSame(
            'inspire',
            ScheduledTaskSignatureExtractor::extract('php artisan inspire')
        );
    }

    public function test_returns_null_for_malformed_input(): void
    {
        $this->assertNull(ScheduledTaskSignatureExtractor::extract(''));
        $this->assertNull(ScheduledTaskSignatureExtractor::extract('gibberish text'));
        $this->assertNull(ScheduledTaskSignatureExtractor::extract('random words no colon'));
    }
}
