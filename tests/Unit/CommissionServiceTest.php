<?php

namespace Tests\Unit;

use App\Exceptions\Commission\CategoryCycleDetectedException;
use App\Exceptions\Commission\CategoryNotFoundException;
use App\Exceptions\Commission\DefaultCommissionRuleNotFoundException;
use App\Exceptions\Commission\InvalidCommissionRateException;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Services\CommissionResult;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommissionService $service;
    private int $slugCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommissionService();
    }

    /* ---------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------- */

    private function cat(string $name, ?int $parentId = null): Category
    {
        return Category::create([
            'name'      => $name,
            'slug'      => 'test-' . (++$this->slugCounter) . '-' . time(),
            'parent_id' => $parentId,
        ]);
    }

    private function rule(?int $categoryId, string $rate, string $effectiveFrom, bool $isActive = true): CommissionRule
    {
        return CommissionRule::create([
            'category_id'    => $categoryId,
            'rate'           => $rate,
            'effective_from' => $effectiveFrom,
            'is_active'      => $isActive,
        ]);
    }

    private function date(string $d = '2026-10-22'): \Carbon\CarbonInterface
    {
        return Carbon::parse($d);
    }

    /* ---------------------------------------------------------------
     * Priority: category > parent > … > root > default
     * ------------------------------------------------------------- */

    public function test_direct_category_rule_takes_priority_over_parent_and_default(): void
    {
        $parent = $this->cat('Parent');
        $child  = $this->cat('Child', $parent->id);

        $this->rule(null, '10.00', '2026-01-01');           // default
        $this->rule($parent->id, '12.00', '2026-01-01');    // parent
        $childRule = $this->rule($child->id, '15.00', '2026-01-01');

        $r = $this->service->calculate($child->id, 200_000, $this->date());

        $this->assertSame('15.00', $r->rate);
        $this->assertSame(30_000, $r->commissionAmount);   // 200 000 × 15%
        $this->assertSame(170_000, $r->vendorAmount);
        $this->assertSame($childRule->id, $r->ruleId);
    }

    public function test_parent_category_fallback(): void
    {
        $parent = $this->cat('Parent');
        $child  = $this->cat('Child', $parent->id);

        $this->rule(null, '10.00', '2026-01-01');
        $parentRule = $this->rule($parent->id, '12.00', '2026-01-01');

        $r = $this->service->calculate($child->id, 100_000, $this->date());

        $this->assertSame('12.00', $r->rate);
        $this->assertSame(12_000, $r->commissionAmount);
        $this->assertSame($parentRule->id, $r->ruleId);
    }

    public function test_multi_level_ancestor_fallback(): void
    {
        $root = $this->cat('Root');
        $mid  = $this->cat('Mid', $root->id);
        $leaf = $this->cat('Leaf', $mid->id);

        $this->rule(null, '10.00', '2026-01-01');
        $rootRule = $this->rule($root->id, '8.00', '2026-01-01');

        $r = $this->service->calculate($leaf->id, 100_000, $this->date());

        $this->assertSame('8.00', $r->rate);
        $this->assertSame(8_000, $r->commissionAmount);
        $this->assertSame($rootRule->id, $r->ruleId);
    }

    public function test_default_rule_fallback_when_no_category_rules(): void
    {
        $cat = $this->cat('Lonely');
        $defaultRule = $this->rule(null, '10.00', '2026-01-01');

        $r = $this->service->calculate($cat->id, 450_000, $this->date());

        $this->assertSame('10.00', $r->rate);
        $this->assertSame(45_000, $r->commissionAmount);
        $this->assertSame(405_000, $r->vendorAmount);
        $this->assertSame($defaultRule->id, $r->ruleId);
    }

    /* ---------------------------------------------------------------
     * Effective-from: latest ≤ date wins; future ignored
     * ------------------------------------------------------------- */

    public function test_picks_latest_effective_from_on_or_before_date(): void
    {
        $cat = $this->cat('Cat');
        $this->rule(null, '5.00', '2026-01-01');

        $this->rule($cat->id, '10.00', '2026-01-01');
        $latest = $this->rule($cat->id, '12.00', '2026-06-01');
        $this->rule($cat->id, '15.00', '2026-12-01'); // future

        $r = $this->service->calculate($cat->id, 100_000, $this->date());

        $this->assertSame('12.00', $r->rate);
        $this->assertSame($latest->id, $r->ruleId);
    }

    public function test_future_only_rule_falls_through_to_default(): void
    {
        $cat = $this->cat('Cat');
        $def = $this->rule(null, '10.00', '2026-01-01');
        $this->rule($cat->id, '20.00', '2027-01-01'); // future only

        $r = $this->service->calculate($cat->id, 100_000, $this->date());

        $this->assertSame('10.00', $r->rate);
        $this->assertSame($def->id, $r->ruleId);
    }

    /* ---------------------------------------------------------------
     * is_active = false → skipped
     * ------------------------------------------------------------- */

    public function test_inactive_rule_is_skipped(): void
    {
        $cat = $this->cat('Cat');
        $this->rule($cat->id, '20.00', '2026-01-01', false);  // inactive
        $def = $this->rule(null, '10.00', '2026-01-01');

        $r = $this->service->calculate($cat->id, 100_000, $this->date());

        $this->assertSame('10.00', $r->rate);
        $this->assertSame($def->id, $r->ruleId);
    }

    /* ---------------------------------------------------------------
     * Rounding — integer-only, half-up
     * ------------------------------------------------------------- */

    public function test_half_up_at_exactly_half_rounds_up(): void
    {
        // 333 335 × 10.00% = 33 333.5  →  rounds UP to 33 334
        $cat = $this->cat('Cat');
        $this->rule(null, '10.00', '2026-01-01');

        $r = $this->service->calculate($cat->id, 333_335, $this->date());

        $this->assertSame(33_334, $r->commissionAmount);
        $this->assertSame(333_335 - 33_334, $r->vendorAmount); // 300 001
    }

    public function test_below_half_rounds_down(): void
    {
        // 333 333 × 10.00% = 33 333.3  →  rounds DOWN to 33 333
        $cat = $this->cat('Cat');
        $this->rule(null, '10.00', '2026-01-01');

        $r = $this->service->calculate($cat->id, 333_333, $this->date());

        $this->assertSame(33_333, $r->commissionAmount);
        $this->assertSame(333_333 - 33_333, $r->vendorAmount); // 300 000
    }

    /* ---------------------------------------------------------------
     * Rate boundaries: 0 & 100 valid; -0.01 & 100.01 rejected
     * ------------------------------------------------------------- */

    public function test_rate_zero_is_valid(): void
    {
        $cat = $this->cat('Cat');
        $this->rule(null, '5.00', '2026-01-01');
        $this->rule($cat->id, '0.00', '2026-01-01');

        $r = $this->service->calculate($cat->id, 100_000, $this->date());

        $this->assertSame('0.00', $r->rate);
        $this->assertSame(0, $r->commissionAmount);
        $this->assertSame(100_000, $r->vendorAmount);
    }

    public function test_rate_100_is_valid(): void
    {
        $cat = $this->cat('Cat');
        $this->rule(null, '5.00', '2026-01-01');
        $this->rule($cat->id, '100.00', '2026-01-01');

        $r = $this->service->calculate($cat->id, 100_000, $this->date());

        $this->assertSame('100.00', $r->rate);
        $this->assertSame(100_000, $r->commissionAmount);
        $this->assertSame(0, $r->vendorAmount);
    }

    public function test_negative_rate_rejected_by_model(): void
    {
        $this->expectException(InvalidCommissionRateException::class);
        $this->expectExceptionMessageMatches('/outside valid range/');

        CommissionRule::create([
            'category_id'    => null,
            'rate'           => '-0.01',
            'effective_from' => '2026-01-01',
            'is_active'      => true,
        ]);
    }

    public function test_rate_above_100_rejected_by_model(): void
    {
        $this->expectException(InvalidCommissionRateException::class);

        CommissionRule::create([
            'category_id'    => null,
            'rate'           => '100.01',
            'effective_from' => '2026-01-01',
            'is_active'      => true,
        ]);
    }

    public function test_invalid_rate_in_db_caught_by_service(): void
    {
        $cat = $this->cat('Cat');

        // Insert bypassing model guard to simulate corrupt data
        DB::table('commission_rules')->insert([
            'category_id'    => $cat->id,
            'category_key'   => $cat->id,
            'rate'           => 105.00,
            'effective_from' => '2026-01-01',
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->expectException(InvalidCommissionRateException::class);
        $this->expectExceptionMessageMatches('/outside valid range/');

        $this->service->calculate($cat->id, 100_000, $this->date());
    }

    /* ---------------------------------------------------------------
     * Exception: no default rule
     * ------------------------------------------------------------- */

    public function test_no_default_rule_throws_descriptive_exception(): void
    {
        $cat = $this->cat('Cat');

        $this->expectException(DefaultCommissionRuleNotFoundException::class);
        $this->expectExceptionMessageMatches('/No default commission rule found/');

        $this->service->calculate($cat->id, 100_000, $this->date());
    }

    /* ---------------------------------------------------------------
     * Exception: category not found
     * ------------------------------------------------------------- */

    public function test_nonexistent_category_throws_exception(): void
    {
        $this->rule(null, '10.00', '2026-01-01');

        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        $this->service->calculate(99_999, 100_000, $this->date());
    }

    /* ---------------------------------------------------------------
     * Exception: category cycle
     * ------------------------------------------------------------- */

    public function test_cycle_in_category_hierarchy_throws_exception(): void
    {
        $catA = $this->cat('A');
        $catB = $this->cat('B', $catA->id);

        // Create cycle: A → B → A  (bypass FK via raw update)
        DB::table('categories')->where('id', $catA->id)->update(['parent_id' => $catB->id]);

        $this->rule(null, '10.00', '2026-01-01');
        // No category-specific rules so the service walks up the tree

        $this->expectException(CategoryCycleDetectedException::class);
        $this->expectExceptionMessageMatches('/Cycle detected/');

        $this->service->calculate($catB->id, 100_000, $this->date());
    }

    /* ---------------------------------------------------------------
     * Documentation examples
     * ------------------------------------------------------------- */

    public function test_doc_example_450000_at_10_percent(): void
    {
        $cat = $this->cat('Skincare');
        $this->rule(null, '10.00', '2026-01-01');

        $r = $this->service->calculate($cat->id, 450_000, $this->date());

        $this->assertSame(45_000, $r->commissionAmount);
        $this->assertSame(405_000, $r->vendorAmount);
    }

    public function test_doc_example_200000_at_15_percent(): void
    {
        $cat = $this->cat('Makeup');
        $this->rule(null, '10.00', '2026-01-01');
        $this->rule($cat->id, '15.00', '2026-01-01');

        $r = $this->service->calculate($cat->id, 200_000, $this->date());

        $this->assertSame(30_000, $r->commissionAmount);
        $this->assertSame(170_000, $r->vendorAmount);
    }

    /* ---------------------------------------------------------------
     * CommissionResult is a proper DTO
     * ------------------------------------------------------------- */

    public function test_result_contains_all_expected_fields(): void
    {
        $cat  = $this->cat('X');
        $rule = $this->rule(null, '10.00', '2026-01-01');

        $r = $this->service->calculate($cat->id, 100_000, $this->date());

        $this->assertInstanceOf(CommissionResult::class, $r);
        $this->assertSame('10.00', $r->rate);
        $this->assertSame(10_000, $r->commissionAmount);
        $this->assertSame(90_000, $r->vendorAmount);
        $this->assertSame($rule->id, $r->ruleId);
    }
}
