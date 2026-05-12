<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class NoAvailableSlotException extends RuntimeException
{
    public function __construct(
        public readonly int $lookaheadDays,
        public readonly array $slots,
    ) {
        parent::__construct(sprintf(
            'No available LinkedIn publish slot found within %d days lookahead. Configured slots: [%s].',
            $lookaheadDays,
            implode(',', $slots),
        ));
    }
}
