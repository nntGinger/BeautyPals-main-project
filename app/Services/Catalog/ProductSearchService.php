<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * UC04 - Public customer search for BeautyPals' REAL Laravel schema (2026 migrations).
 *
 * Business rules:
 * - Only products.status=approved from vendors.status=approved.
 * - Only active SKUs contribute to price and stock availability.
 * - Price is the minimum active-SKU price within the requested price range.
 * - Stock is available when a non-expired active batch has unreserved units.
 * - A product with no available stock can still be found unless in_stock=1.
 * - Each product appears once, regardless of its number of SKUs/batches.
 * - Empty brand is allowed: products.brand_id is nullable in the migration.
 *
 * SQL identifiers and sort options are server-defined; all user input is bound.
 * Inventory is informational here: checkout must revalidate stock atomically.
 */
final class ProductSearchService
{
    public function search(array $filters): LengthAwarePaginator
    {
        $this->requireApplicationSchema();

        $today = now()->toDateString();

        // Aggregate qualifying SKUs before joining products: avoid duplicate rows
        // and avoid multiplying prices when several inventory batches exist.
        $offers = DB::table('skus as s')
            ->where('s.is_active', true)
            ->select('s.product_id')
            ->selectRaw('MIN(s.price) AS min_price')
            ->selectRaw(<<<'SQL'
                MAX(CASE WHEN EXISTS (
                    SELECT 1
                    FROM batches AS bt
                    WHERE bt.sku_id = s.id
                      AND bt.status = ?
                      AND bt.exp_date >= ?
                      AND bt.qty_on_hand > bt.qty_reserved
                ) THEN 1 ELSE 0 END) AS in_stock
                SQL, ['active', $today]);

        // A qualifying price is an actual ACTIVE SKU's price; products has no
        // price column. Applying price constraints before grouping is essential.
        if (isset($filters['min_price'])) {
            $offers->where('s.price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $offers->where('s.price', '<=', $filters['max_price']);
        }

        // In-stock-only means at least one of the matching-price SKUs has a
        // sellable batch. The displayed min_price then belongs to that SKU set.
        if (!empty($filters['in_stock'])) {
            $offers->whereExists(static function (Builder $stock) use ($today): void {
                $stock->selectRaw('1')
                    ->from('batches as bt')
                    ->whereColumn('bt.sku_id', 's.id')
                    ->where('bt.status', 'active')
                    ->whereDate('bt.exp_date', '>=', $today)
                    ->whereColumn('bt.qty_on_hand', '>', 'bt.qty_reserved');
            });
        }
        $offers->groupBy('s.product_id');

        $query = DB::table('products as p')
            ->join('vendors as v', 'v.id', '=', 'p.vendor_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->joinSub($offers, 'offer', 'offer.product_id', '=', 'p.id')
            ->where('p.status', 'approved')
            ->where('v.status', 'approved')
            ->select([
                'p.id', 'p.name', 'p.slug', 'p.description',
                'p.vendor_id', 'p.brand_id', 'p.category_id', 'p.created_at',
                'v.store_name as vendor_name',
                'b.name as brand_name', 'c.name as category_name',
                'offer.min_price as price', 'offer.in_stock',
            ]);

        // One image per product, deterministic ordering; no N+1 selects.
        $query->selectSub(
            DB::table('product_images as pi')
                ->select('pi.path')
                ->whereColumn('pi.product_id', 'p.id')
                ->orderBy('pi.sort')
                ->orderBy('pi.id')
                ->limit(1),
            'image_path'
        );

        $keyword = (string) ($filters['q'] ?? '');
        if ($keyword !== '') {
            // The escape character (!) is intentionally portable across
            // SQLite tests and MySQL runtime, unlike driver-dependent backslashes.
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
            $pattern = '%'.$escaped.'%';
            $query->where(static function (Builder $where) use ($pattern): void {
                foreach (['p.name', 'p.description', 'p.slug', 'b.name', 'c.name'] as $column) {
                    $where->orWhereRaw($column." LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }

        if (isset($filters['brand_id'])) {
            $query->where('p.brand_id', (int) $filters['brand_id']);
        }
        if (isset($filters['category_id'])) {
            $categoryId = (int) $filters['category_id'];
            $includeChildren = (bool) ($filters['include_descendants'] ?? true);
            $ids = $includeChildren
                ? $this->categoryAndDescendants($categoryId)
                : [$categoryId];
            $query->whereIn('p.category_id', $ids);
        }

        // No dynamic orderBy column names from HTTP requests.
        switch ($filters['sort'] ?? 'newest') {
            case 'price_asc':
                $query->orderBy('offer.min_price');
                break;
            case 'price_desc':
                $query->orderByDesc('offer.min_price');
                break;
            case 'name_asc':
                $query->orderBy('p.name');
                break;
            case 'name_desc':
                $query->orderByDesc('p.name');
                break;
            default:
                $query->orderByDesc('p.created_at');
        }
        $query->orderBy('p.id'); // Stable pagination when primary sort ties.

        return $query->paginate((int) ($filters['per_page'] ?? 12))
            ->appends($filters);
    }

    /** Include the chosen category and its children (iterative, cycle-safe). */
    private function categoryAndDescendants(int $root): array
    {
        $pairs = DB::table('categories')->get(['id', 'parent_id']);
        if ($pairs->count() > 5000) {
            throw new LogicException('UC04 category hierarchy exceeds the supported size.');
        }

        $children = [];
        $rootExists = false;
        foreach ($pairs as $category) {
            $id = (int) $category->id;
            if ($id === $root) {
                $rootExists = true;
            }
            if ($category->parent_id !== null) {
                $children[(int) $category->parent_id][] = $id;
            }
        }
        if (!$rootExists) {
            return [];
        }

        $visited = [];
        $pending = [$root];
        while ($pending) {
            $current = array_pop($pending);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            foreach ($children[$current] ?? [] as $child) {
                $pending[] = $child;
            }
        }
        return array_keys($visited);
    }

    /** Fail closed if code is accidentally deployed against another schema. */
    private function requireApplicationSchema(): void
    {
        $required = [
            'products' => ['id', 'vendor_id', 'brand_id', 'category_id', 'name', 'slug', 'description', 'status', 'created_at'],
            'vendors' => ['id', 'store_name', 'status'],
            'brands' => ['id', 'name'],
            'categories' => ['id', 'parent_id', 'name'],
            'skus' => ['id', 'product_id', 'price', 'is_active'],
            'batches' => ['sku_id', 'exp_date', 'status', 'qty_on_hand', 'qty_reserved'],
            'product_images' => ['id', 'product_id', 'path', 'sort'],
        ];
        foreach ($required as $table => $columns) {
            if (!Schema::hasTable($table) || !Schema::hasColumns($table, $columns)) {
                throw new LogicException("UC04 schema mismatch: {$table} missing required columns.");
            }
        }
    }
}
