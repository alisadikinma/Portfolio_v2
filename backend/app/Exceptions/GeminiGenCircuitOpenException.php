<?php

namespace App\Exceptions;

use Carbon\Carbon;
use RuntimeException;

class GeminiGenCircuitOpenException extends RuntimeException
{
    public function __construct(
        public ?Carbon $openedAt = null,
        public ?Carbon $nextProbeAt = null,
    ) {
        parent::__construct('GeminiGen circuit breaker is OPEN — service paused.');
    }
}
