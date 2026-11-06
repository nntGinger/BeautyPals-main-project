<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreProductRequest;
use App\Http\Requests\Vendor\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $products = $request->user()->vendor->products()
            ->with(['category', 'brand', 'images'])
            ->withCount('skus')
            ->withMin('skus', 'price')
            ->withMax('skus', 'price')
            ->orderByDesc('id')
            ->paginate(20);

        return view('vendor.products.index', compact('products'));
    }

    public function show(Request $request, string $product): View
    {
        $product = $request->user()->vendor->products()->findOrFail($product);
        $this->authorize('viewManaged', $product);
        $product->load(['category', 'brand', 'skus', 'ingredients', 'images']);

        return view('vendor.products.show', compact('product'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('vendor.products.create', $this->formOptions());
    }

    public function store(StoreProductRequest $request, ProductService $service): RedirectResponse
    {
        $product = $service->save($request->user(), $request->payload());

        return redirect()->route('vendor.products.edit', $product)->with('success', 'Đã tạo sản phẩm nháp.');
    }

    public function edit(Request $request, string $product): View|RedirectResponse
    {
        $product = $request->user()->vendor->products()->findOrFail($product);
        $this->authorize('update', $product);
        $product->load(['skus', 'images', 'ingredients']);

        return view('vendor.products.edit', ['product' => $product] + $this->formOptions($product));
    }

    public function update(UpdateProductRequest $request, string $product, ProductService $service): RedirectResponse
    {
        $product = $service->save($request->user(), $request->payload(), (int) $product);

        return redirect()->route('vendor.products.edit', $product)->with('success', 'Đã lưu thay đổi sản phẩm.');
    }

    private function formOptions(?Product $product = null): array
    {
        $selectedIngredients = session()->hasOldInput() ? old('ingredients', []) : ($product?->ingredients->map(fn ($ingredient) => [
            'ingredient_id' => $ingredient->id, 'position' => $ingredient->pivot->position,
        ])->all() ?? []);
        $selectedIngredients = is_array($selectedIngredients) ? $selectedIngredients : [];
        $selectedIngredients = array_filter($selectedIngredients, fn ($row, $key) => is_array($row) && is_int($key) && $key >= 0, ARRAY_FILTER_USE_BOTH);
        $ids = collect($selectedIngredients)->pluck('ingredient_id')->filter(fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))->take(100)->all();

        return ['categories' => Category::orderBy('name')->get(['id', 'name']), 'brands' => Brand::orderBy('name')->get(['id', 'name']),
            'selectedIngredients' => $selectedIngredients,
            'ingredientNames' => Ingredient::whereIn('id', $ids)->get(['id', 'inci_name', 'display_name'])->keyBy('id')];
    }

    public function destroy(Request $request, string $product, ProductService $service): RedirectResponse
    {
        $service->destroy($request->user(), (int) $product);

        return redirect()->route('vendor.products.index')->with('success', 'Đã xóa sản phẩm nháp.');
    }
}
