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
        } elseif (is_string($value) && $this->stringCarriesOffset($value)) {
            // '2026-05-13T12:00:00+07:00' would otherwise keep its +07:00 wall-clock.
            $value = $this->asDateTime($value)
                ->setTimezone((string) config('app.timezone'));
        }

        return parent::fromDateTime($value);
    }

    /**
     * Only strings with an explicit offset/Z are converted; naive strings are
     * trusted to already be in app.timezone (existing behaviour).
     */
    private function stringCarriesOffset(string $value): bool
    {
        return (bool) preg_match('/(?:[zZ]|[+-]\d{2}:?\d{2})$/', trim($value));
    }
}
