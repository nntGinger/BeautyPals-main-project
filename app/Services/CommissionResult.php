<?php

namespace App\Services;

/**
 * Immutable result of a single line-item commission calculation.
 */
class CommissionResult
{
    public function __construct(
        /** Commission rate as a decimal string, e.g. "10.00" */
        public readonly string $rate,
        /** Platform commission amount in VND (integer) */
        public readonly int $commissionAmount,
        /** Vendor payout amount in VND (integer): lineTotal − commissionAmount */
        public readonly int $vendorAmount,
        /** ID of the CommissionRule that was applied, null only when no rule matched */
        public readonly ?int $ruleId,
    ) {}
}
