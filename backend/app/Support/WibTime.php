<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Admin UIs send `datetime-local` values (no offset) that mean WIB wall-clock.
 * Storage stays UTC; this turns an input string into an unambiguous Carbon.
 */
final class WibTime
{
    public const ZONE = 'Asia/Jakarta';

    /**
     * Naive strings are read as WIB; strings carrying an offset or Z keep it.
     */
    public static function parse(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // Carbon::parse($v, $tz) applies $tz only when the string has no offset.
        return Carbon::parse($value, self::ZONE);
    }
}
