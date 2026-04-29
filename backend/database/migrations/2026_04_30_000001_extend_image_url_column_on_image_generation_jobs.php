<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widen image_generation_jobs.image_url from varchar(255) → text.
 *
 * Mirrors the Apr 14 widen of remote_url. R2 signed URLs returned by
 * GeminiGen carry ~700-830 chars of AWS signature query params
 * (X-Amz-Signature, X-Amz-Credential, X-Amz-Date, ...). When
 * downloadAndStore() falls back to returning the remote URL — e.g. on
 * a storage put failure — varchar(255) overflows under MySQL
 * STRICT_TRANS_TABLES, throwing SQLSTATE[22001] "Data too long for
 * column 'image_url'". The exception bubbles into the polling cron's
 * outer catch, so the job stays in 'processing' and gets re-polled
 * every minute, flooding logs with "[LinkedInCarouselImage] storage
 * put failed".
 *
 * Raw ALTER (MySQL only) — Laravel ->change() needs doctrine/dbal,
 * which this project doesn't pull in. SQLite test suite stores all
 * strings as TEXT under the hood, so the MODIFY is a no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE image_generation_jobs MODIFY COLUMN image_url TEXT NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE image_generation_jobs MODIFY COLUMN image_url VARCHAR(255) NULL');
        }
    }
};
