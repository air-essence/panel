<?php

namespace App\Services\Discrepancies;

use App\Enums\Severity;
use App\Models\Order;

final class Discrepancy
{
    public function __construct(
        public readonly string $rule,
        public readonly Severity $severity,
        public readonly Order $order,
        public readonly string $message,
    ) {}
}
