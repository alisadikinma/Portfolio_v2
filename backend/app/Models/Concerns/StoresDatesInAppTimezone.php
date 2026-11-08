<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Store DateTimeInterface values in config('app.timezone').
 *
 * Eloquent's fromDateTime() formats a Carbon with its OWN zone and never
 * converts it. A writer that passes an Asia/Jakarta Carbon therefore stored the
 * WIB wall-clock in a UTC app, and the publish cron (compares to now() in UTC)
 * fired 7 hours late. Converting here is the single fix point — writers keep
 * passing whatever zone they like.
 */
trait StoresDatesInAppTimezone
{
    public function fromDateTime($value)
    {
        if ($value instanceof DateTimeInterface) {
            // Copy: never mutate the caller's object.
            $value = Carbon::instance($value)
                ->setTimezone((string) config('app.timezone'));
        }

        return parent::fromDateTime($value);
    }
}
