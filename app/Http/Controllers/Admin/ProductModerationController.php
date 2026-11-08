<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Services\Catalog\ProductModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductModerationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('moderateAny', Product::class);
        $data = $request->validate(['status' => ['nullable', Rule::in(['draft', 'pending', 'approved', 'rejected', 'hidden'])],
            'q' => ['nullable', 'string', 'max:200']]);
        $q = trim($data['q'] ?? '');
        $products = Product::with(['vendor', 'brand'])->withCount('skus')
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query->whereRaw("name LIKE ? ESCAPE '\\\\'", ['%'.addcslashes($q, '\\%_').'%']))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function show(string $product): View
    {
        $product = Product::with(['vendor', 'brand', 'category', 'skus', 'images', 'ingredients'])->findOrFail($product);
        $this->authorize('moderate', $product);
        $history = AuditLog::with('actor')->where('entity_type', 'product')->where('entity_id', $product->id)
            ->orderByDesc('id')->paginate(10);

        return view('admin.products.show', compact('product', 'history'));
    }

    public function decide(Request $request, string $product, string $decision, ProductModerationService $service): RedirectResponse
    {
        $product = $service->decide($request->user(), (int) $product, $decision, $request->input('reason'), $request->ip());
        $messages = ['approve' => 'Đã duyệt sản phẩm.', 'reject' => 'Đã từ chối sản phẩm.', 'hide' => 'Đã ẩn sản phẩm.', 'restore' => 'Đã hiện lại sản phẩm.'];

        return redirect()->route('admin.products.show', $product)->with('success', $messages[$decision]);
    }
}
