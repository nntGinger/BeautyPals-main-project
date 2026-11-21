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
    /**
     * Display the current customer's cart.
     */
    public function index(
        Request $request,
        CartService $cartService
    ): View {
        $userId = (int) $request->user()->id;

        $cartData = $cartService->getCartGroupedByVendor(
            $userId
        );

        return view('customer.cart.index', [
            'cartData' => $cartData,
        ]);
    }

    /**
     * Add an SKU to the cart.
     */
    public function store(
        Request $request,
        CartService $cartService
    ): RedirectResponse {
        $validated = $request->validate([
            'sku_id' => ['required', 'integer', 'min:1'],
            'qty' => [
                'required',
                'integer',
                'min:1',
                'max:4294967295',
            ],
        ], [
            'sku_id.required' => 'Vui lòng chọn sản phẩm.',
            'qty.required' => 'Vui lòng nhập số lượng.',
            'qty.min' => 'Số lượng phải lớn hơn 0.',
            'qty.max' => 'Số lượng vượt giới hạn cho phép.',
        ]);

        try {
            $cartService->addItem(
                (int) $request->user()->id,
                (int) $validated['sku_id'],
                (int) $validated['qty']
            );
        } catch (CartException $exception) {
            throw ValidationException::withMessages([
                'sku_id' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('customer.cart.index')
            ->with(
                'status',
                'Đã thêm sản phẩm vào giỏ hàng.'
            );
    }

    /**
     * Update the quantity of an existing SKU.
     */
    public function update(
        Request $request,
        int $skuId,
        CartService $cartService
    ): RedirectResponse {
        $validated = $request->validate([
            'qty' => [
                'required',
                'integer',
                'min:1',
                'max:4294967295',
            ],
        ], [
            'qty.required' => 'Vui lòng nhập số lượng.',
            'qty.min' => 'Số lượng phải lớn hơn 0.',
            'qty.max' => 'Số lượng vượt giới hạn cho phép.',
        ]);

        try {
            $cartService->updateQuantity(
                (int) $request->user()->id,
                $skuId,
                (int) $validated['qty']
            );
        } catch (CartException $exception) {
            throw ValidationException::withMessages([
                'qty' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('customer.cart.index')
            ->with(
                'status',
                'Đã cập nhật số lượng sản phẩm.'
            );
    }

    /**
     * Remove an SKU from the cart.
     */
    public function destroy(
        Request $request,
        int $skuId,
        CartService $cartService
    ): RedirectResponse {
        try {
            $cartService->removeItem(
                (int) $request->user()->id,
                $skuId
            );
        } catch (CartException $exception) {
            return redirect()
                ->route('customer.cart.index')
                ->with(
                    'error',
                    $exception->getMessage()
                );
        }

        return redirect()
            ->route('customer.cart.index')
            ->with(
                'status',
                'Đã xóa sản phẩm khỏi giỏ hàng.'
            );
    }
}
