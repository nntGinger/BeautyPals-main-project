<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim($request->string('q')->toString());
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $brandId = $request->filled('brand_id') ? (int) $request->input('brand_id') : null;
        $withIngredient = trim($request->string('with_ingredient')->toString());
        $withoutIngredient = trim($request->string('without_ingredient')->toString());
        $minPrice = $request->filled('min_price') ? max(0, (int) $request->input('min_price')) : null;
        $maxPrice = $request->filled('max_price') ? max(0, (int) $request->input('max_price')) : null;
        $sort = $request->string('sort')->toString();

        $products = Product::query()
            ->approved()
            ->with([
                'vendor',
                'brand',
                'category',
                'images',
                'skus' => fn ($skus) => $skus->where('is_active', true),
            ])
            ->searchKeyword($q)
            ->inCategory($categoryId)
            ->ofBrand($brandId)
            ->withIngredient($withIngredient !== '' ? $withIngredient : null)
            ->withoutIngredient($withoutIngredient !== '' ? $withoutIngredient : null)
            ->priceBetween($minPrice, $maxPrice)
            ->sortByOption($sort)
            ->paginate(12)
            ->withQueryString();

        $categories = Category::query()->orderBy('name')->get();
        $brands = Brand::query()->orderBy('name')->get();
        $ingredients = Ingredient::query()->orderBy('display_name')->get(['id', 'inci_name', 'display_name']);

        return view('catalog.index', compact(
            'products',
            'categories',
            'brands',
            'ingredients',
            'q',
            'categoryId',
            'brandId',
            'withIngredient',
            'withoutIngredient',
            'minPrice',
            'maxPrice',
            'sort',
        ));
    }

    public function show(string $slugOrId): View
    {
        $today = now()->toDateString();

        $product = Product::query()
            ->approved()
            ->where(function ($query) use ($slugOrId): void {
                $query->where('slug', $slugOrId);
                if (ctype_digit($slugOrId)) {
                    $query->orWhere('id', (int) $slugOrId);
                }
            })
            ->with([
                'vendor',
                'brand',
                'category',
                'images',
                'skinTypes',
                'ingredients.aliases',
                'skus' => fn ($q) => $q->where('is_active', true)->with([
                    'batches' => fn ($b) => $b->where('status', 'active')
                        ->whereDate('exp_date', '>=', $today)
                        ->orderBy('exp_date', 'asc')
                        ->orderBy('id', 'asc'),
                ]),
            ])
            ->firstOrFail();

        $totalAvailable = 0;
        $nearestExpDate = null;

        foreach ($product->skus as $sku) {
            /** @var Sku $sku */
            $available = (int) $sku->batches->sum(
                fn (Batch $b) => max(0, (int) $b->qty_on_hand - (int) $b->qty_reserved),
            );
            $skuNearestExp = $sku->batches
                ->filter(fn (Batch $b) => ((int) $b->qty_on_hand - (int) $b->qty_reserved) > 0)
                ->first()?->exp_date;

            $sku->setAttribute('available_stock', $available);
            $sku->setAttribute('nearest_exp_date', $skuNearestExp);

            $totalAvailable += $available;
            if ($skuNearestExp !== null && ($nearestExpDate === null || $skuNearestExp->lt($nearestExpDate))) {
                $nearestExpDate = $skuNearestExp;
            }
        }

        return view('catalog.show', compact('product', 'totalAvailable', 'nearestExpDate'));
    }
}
