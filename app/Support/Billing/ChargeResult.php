<?php

namespace App\Support\Billing;

final class ChargeResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $reference = null,
        public readonly ?string $failureMessage = null,
    ) {
    }
}
