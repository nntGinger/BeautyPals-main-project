<?php

namespace App\Services;

use App\Exceptions\Commission\CategoryCycleDetectedException;
use App\Exceptions\Commission\CategoryNotFoundException;
use App\Exceptions\Commission\DefaultCommissionRuleNotFoundException;
use App\Exceptions\Commission\InvalidCommissionRateException;
use App\Models\Category;
use App\Models\CommissionRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class CommissionService
{
    /** Maximum ancestor levels to traverse before giving up. */
    private const MAX_DEPTH = 10;

    /**
     * Calculate the platform commission for a single order line-item.
     *
     * Priority: category rule → parent category rule → … → root → default rule.
     * Rounding: half-up to the nearest VND, computed with pure integer arithmetic.
     *
     * @param  int                   $categoryId  Product's category ID
     * @param  int                   $lineTotal   Line-item total in VND (integer)
     * @param  CarbonInterface|null  $at          Calculation date (defaults to today)
     *
     * @throws CategoryNotFoundException
     * @throws CategoryCycleDetectedException
     * @throws DefaultCommissionRuleNotFoundException
     * @throws InvalidCommissionRateException
     */
    public function calculate(
        int $categoryId,
        int $lineTotal,
        ?CarbonInterface $at = null,
    ): CommissionResult {
        $at = $at ?? Carbon::today();
        $dateStr = $at->toDateString();

        // 1. Verify category exists
        $category = Category::find($categoryId);
        if ($category === null) {
            throw new CategoryNotFoundException(
                "Category [{$categoryId}] does not exist."
            );
        }

        // 2. Try direct category, then walk ancestors
        $rule = $this->findRuleForCategory($categoryId, $dateStr);

        if ($rule === null) {
            $visited = [$categoryId];
            $currentParentId = $category->parent_id;
            $depth = 0;

            while ($currentParentId !== null && $depth < self::MAX_DEPTH) {
                if (in_array($currentParentId, $visited, true)) {
                    throw new CategoryCycleDetectedException(
                        "Cycle detected in category hierarchy at category [{$currentParentId}]."
                    );
                }
                $visited[] = $currentParentId;
                $depth++;

                $rule = $this->findRuleForCategory($currentParentId, $dateStr);
                if ($rule !== null) {
                    break;
                }

                $ancestor = Category::find($currentParentId);
                if ($ancestor === null) {
                    break; // orphan parent_id — fall through to default
                }
                $currentParentId = $ancestor->parent_id;
            }
        }

        // 3. Fallback to default rule (category_id IS NULL)
        if ($rule === null) {
            $rule = $this->findDefaultRule($dateStr);
            if ($rule === null) {
                throw new DefaultCommissionRuleNotFoundException(
                    "No default commission rule found effective on or before {$dateStr}."
                );
            }
        }

        // 4. Validate rate read from DB
        $rateStr = (string) $rule->rate;
        if (!is_numeric($rateStr)
            || bccomp($rateStr, '0', 2) < 0
            || bccomp($rateStr, '100.00', 2) > 0
        ) {
            throw new InvalidCommissionRateException(
                "Commission rate [{$rateStr}] on rule [{$rule->id}] is outside valid range [0, 100]."
            );
        }

        // 5. Integer-only commission calculation with half-up rounding.
        //    Convert rate (string like "10.00") to hundredths-of-percent integer
        //    so all arithmetic stays in the integer domain.
        //    commission = round_half_up(lineTotal × rate / 100)
        //               = intdiv(lineTotal × rateHundredths + 5000, 10000)
        $rateHundredths = (int) bcmul($rateStr, '100', 0);
        $raw            = $lineTotal * $rateHundredths;         // commission × 10 000
        $commission     = intdiv($raw + 5000, 10000);
        $vendorAmount   = $lineTotal - $commission;

        return new CommissionResult(
            rate: $rateStr,
            commissionAmount: $commission,
            vendorAmount: $vendorAmount,
            ruleId: $rule->id,
        );
    }

    /**
     * Find the most recent active rule for a specific category effective on or before $dateStr.
     */
    private function findRuleForCategory(int $categoryId, string $dateStr): ?CommissionRule
    {
        return CommissionRule::where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('effective_from', '<=', $dateStr)
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Find the most recent active default rule (category_id IS NULL) effective on or before $dateStr.
     */
    private function findDefaultRule(string $dateStr): ?CommissionRule
    {
        return CommissionRule::whereNull('category_id')
            ->where('is_active', true)
            ->where('effective_from', '<=', $dateStr)
            ->orderByDesc('effective_from')
            ->first();
    }
}
