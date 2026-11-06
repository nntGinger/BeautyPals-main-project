<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\CartException;
use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request, CartService $cartService): View
    {
        $cartData = $cartService->getCartGroupedByVendor((int) $request->user()->id);

        return view('customer.cart.index', compact('cartData'));
    }

    public function store(Request $request, CartService $cartService): RedirectResponse
    {
        $validated = $request->validate(
            [
                'sku_id' => ['required', 'integer'],
                'qty' => ['required', 'integer', 'min:1'],
            ],
            [
                'sku_id.required' => 'Vui lòng chọn phân loại sản phẩm (SKU).',
                'qty.required' => 'Vui lòng nhập số lượng.',
                'qty.min' => 'Số lượng phải từ 1 trở lên.',
            ],
        );

        try {
            $cartService->addItem(
                (int) $request->user()->id,
                (int) $validated['sku_id'],
                (int) $validated['qty'],
            );
        } catch (CartException $e) {
            throw ValidationException::withMessages([
                'sku_id' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('customer.cart.index')
            ->with('status', 'Đã thêm sản phẩm vào giỏ hàng.');
    }

    public function update(Request $request, int $skuId, CartService $cartService): RedirectResponse
    {
        $validated = $request->validate(
            [
                'qty' => ['required', 'integer', 'min:1'],
            ],
            [
                'qty.required' => 'Vui lòng nhập số lượng.',
                'qty.min' => 'Số lượng phải từ 1 trở lên.',
            ],
        );

        try {
            $cartService->updateQuantity(
                (int) $request->user()->id,
                $skuId,
                (int) $validated['qty'],
            );
        } catch (CartException $e) {
            throw ValidationException::withMessages([
                'qty' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('customer.cart.index')
            ->with('status', 'Đã cập nhật số lượng giỏ hàng.');
    }

    public function destroy(Request $request, int $skuId, CartService $cartService): RedirectResponse
    {
        try {
            $cartService->removeItem((int) $request->user()->id, $skuId);
        } catch (CartException $e) {
            return redirect()
                ->route('customer.cart.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('customer.cart.index')
            ->with('status', 'Đã xóa sản phẩm khỏi giỏ hàng.');
    }
}
