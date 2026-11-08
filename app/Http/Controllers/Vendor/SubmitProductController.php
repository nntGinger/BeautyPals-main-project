<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubmitProductController extends Controller
{
    public function __invoke(Request $request, string $product, ProductService $service): RedirectResponse
    {
        $product = $service->submit($request->user(), (int) $product, $request->ip());

        return redirect()->route('vendor.products.show', $product)->with('success', 'Đã gửi sản phẩm để chờ duyệt.');
    }
}
