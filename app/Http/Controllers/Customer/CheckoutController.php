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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    /**
     * Display the COD checkout page.
     */
    public function index(
        Request $request,
        CartService $cartService
    ): View|RedirectResponse {
        $userId = (int) $request->user()->id;

        // Get the customer's multi-vendor cart.
        $cartData = $cartService->getCartGroupedByVendor(
            $userId
        );

        // Prevent checkout when the cart is empty.
        if (empty($cartData['vendors'])) {
            return redirect()
                ->route('customer.cart.index')
                ->with(
                    'error',
                    'Giỏ hàng đang trống. Vui lòng thêm sản phẩm trước khi đặt hàng.'
                );
        }

        // Get only addresses owned by this customer.
        $addresses = Address::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        // Generate a unique key for the checkout attempt.
        $idempotencyKey = (string) Str::uuid();

        return view('customer.checkout.index', [
            'cartData' => $cartData,
            'addresses' => $addresses,
            'idempotencyKey' => $idempotencyKey,
        ]);
    }

    /**
     * Submit a COD order.
     *
     * Actual order creation belongs to CheckoutService.
     */
    public function store(
        Request $request
    ): RedirectResponse {
        $userId = (int) $request->user()->id;

        // Validate the selected address and request key.
        $validated = $request->validate([
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')
                    ->where('user_id', $userId),
            ],
            'idempotency_key' => [
                'required',
                'uuid',
            ],
        ], [
            'address_id.required' =>
                'Vui lòng chọn địa chỉ nhận hàng.',

            'address_id.exists' =>
                'Địa chỉ nhận hàng không hợp lệ.',

            'idempotency_key.required' =>
                'Thiếu mã định danh giao dịch.',

            'idempotency_key.uuid' =>
                'Mã định danh giao dịch không hợp lệ.',
        ]);

        // Do not place orders until backend dependencies
        // and the order-detail page are available.
        if (
            ! class_exists(CheckoutService::class)
            || ! Route::has('customer.orders.show')
        ) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'Chức năng đặt hàng đang chờ hoàn tất tích hợp hệ thống.'
                );
        }

        try {
            // Delegate checkout business logic to its service.
            $checkoutService = app(CheckoutService::class);

            $order = $checkoutService->place(
                $userId,
                (int) $validated['address_id'],
                (string) $validated['idempotency_key']
            );

        } catch (CartException|InventoryException $exception) {
            throw ValidationException::withMessages([
                'cart' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('customer.orders.show', $order)
            ->with(
                'status',
                'Đặt hàng COD thành công! Mã đơn hàng: '
                . $order->order_code
            );
    }
}
