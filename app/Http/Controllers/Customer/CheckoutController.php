<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\CartException;
use App\Exceptions\InventoryException;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function index(Request $request, CartService $cartService): View|RedirectResponse
    {
        $userId = (int) $request->user()->id;
        $cartData = $cartService->getCartGroupedByVendor($userId);

        if (empty($cartData['vendors'])) {
            return redirect()
                ->route('customer.cart.index')
                ->with('error', 'Giỏ hàng của bạn đang trống, vui lòng chọn sản phẩm trước khi thanh toán.');
        }

        $addresses = Address::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        $idempotencyKey = (string) Str::uuid();

        return view('customer.checkout.index', compact('cartData', 'addresses', 'idempotencyKey'));
    }

    public function store(Request $request, CheckoutService $checkoutService): RedirectResponse
    {
        $validated = $request->validate(
            [
                'address_id' => ['required', 'integer'],
                'idempotency_key' => ['required', 'string', 'max:36'],
            ],
            [
                'address_id.required' => 'Vui lòng chọn địa chỉ nhận hàng.',
                'idempotency_key.required' => 'Thiếu khóa định danh giao dịch (idempotency_key).',
            ],
        );

        try {
            $order = $checkoutService->place(
                (int) $request->user()->id,
                (int) $validated['address_id'],
                (string) $validated['idempotency_key'],
            );
        } catch (CartException|InventoryException $e) {
            throw ValidationException::withMessages([
                'cart' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('customer.orders.show', $order)
            ->with('status', "Đặt hàng COD thành công! Mã đơn hàng: {$order->order_code}");
    }
}
