<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\VendorOrder;
use App\Services\Inventory\ReservationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly CommissionService $commissionService,
        private readonly ReservationService $reservationService,
    ) {}

    /**
     * Place a multi-vendor COD order from the customer's cart inside a single transaction.
     *
     * Idempotent by $idempotencyKey: submitting the same key returns the existing Order.
     */
    public function place(
        int $customerId,
        int $addressId,
        string $idempotencyKey,
    ): Order {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 36) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Khóa định danh đơn hàng (idempotency_key) không hợp lệ.',
            ]);
        }

        $existing = Order::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            if ((int) $existing->customer_id !== $customerId) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Khóa định danh đơn hàng đã được sử dụng.',
                ]);
            }

            return $existing->load(['vendorOrders.items.allocations', 'payments']);
        }

        try {
            return DB::transaction(function () use (
                $customerId,
                $addressId,
                $idempotencyKey,
            ): Order {
                $existingInTx = Order::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingInTx !== null) {
                    if ((int) $existingInTx->customer_id !== $customerId) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'Khóa định danh đơn hàng đã được sử dụng.',
                        ]);
                    }

                    return $existingInTx->load(['vendorOrders.items.allocations', 'payments']);
                }

                $address = Address::query()
                    ->whereKey($addressId)
                    ->where('user_id', $customerId)
                    ->first();

                if ($address === null) {
                    throw ValidationException::withMessages([
                        'address_id' => 'Vui lòng chọn địa chỉ giao hàng hợp lệ.',
                    ]);
                }

                $cart = Cart::query()
                    ->where('user_id', $customerId)
                    ->lockForUpdate()
                    ->first();

                $cartItems = $cart !== null
                    ? CartItem::query()
                        ->where('cart_id', $cart->id)
                        ->with('sku.product.vendor.owner')
                        ->orderBy('sku_id', 'asc')
                        ->lockForUpdate()
                        ->get()
                    : collect();

                if ($cartItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' => 'Giỏ hàng của bạn đang trống.',
                    ]);
                }

                $vendorGroups = [];
                $orderSubtotal = 0;

                foreach ($cartItems as $item) {
                    $sku = $item->sku;
                    if ($sku === null) {
                        throw CartException::skuNotFound((int) $item->sku_id);
                    }
                    if (! $sku->is_active) {
                        throw CartException::skuInactive((int) $sku->id);
                    }

                    $product = $sku->product;
                    if ($product === null || $product->status !== 'approved') {
                        throw CartException::productUnavailable((int) $sku->product_id);
                    }

                    $vendor = $product->vendor;
                    if (
                        $vendor === null
                        || $vendor->status !== 'approved'
                        || ($vendor->owner !== null && $vendor->owner->isLocked())
                    ) {
                        throw CartException::vendorUnavailable((int) $product->vendor_id);
                    }

                    $vendorId = (int) $vendor->id;
                    $unitPrice = (int) $sku->price;
                    $qty = (int) $item->qty;
                    $lineTotal = $unitPrice * $qty;

                    if (! isset($vendorGroups[$vendorId])) {
                        $vendorGroups[$vendorId] = [
                            'vendor_id' => $vendorId,
                            'subtotal' => 0,
                            'items' => [],
                        ];
                    }

                    $vendorGroups[$vendorId]['subtotal'] += $lineTotal;
                    $vendorGroups[$vendorId]['items'][] = [
                        'sku' => $sku,
                        'product' => $product,
                        'unit_price' => $unitPrice,
                        'qty' => $qty,
                        'line_total' => $lineTotal,
                    ];

                    $orderSubtotal += $lineTotal;
                }

                ksort($vendorGroups);

                $addressText = implode(', ', array_filter([
                    $address->line,
                    $address->ward,
                    $address->district,
                    $address->city,
                ]));

                $order = Order::query()->create([
                    'order_code' => $this->generateUniqueOrderCode(),
                    'customer_id' => $customerId,
                    'receiver' => $address->receiver,
                    'phone' => $address->phone,
                    'address_text' => $addressText,
                    'subtotal' => $orderSubtotal,
                    'shipping_total' => 0,
                    'discount_total' => 0,
                    'grand_total' => $orderSubtotal,
                    'payment_method' => 'cod',
                    'payment_status' => 'pending',
                    'status' => 'pending',
                    'voucher_id' => null,
                    'idempotency_key' => $idempotencyKey,
                    'placed_at' => now(),
                ]);

                foreach ($vendorGroups as $vendorId => $group) {
                    $vendorOrder = VendorOrder::query()->create([
                        'order_id' => $order->id,
                        'vendor_id' => $vendorId,
                        'vo_code' => $this->generateUniqueVendorOrderCode(),
                        'status' => 'pending',
                        'subtotal' => $group['subtotal'],
                        'shipping_fee' => 0,
                        'discount' => 0,
                        'total' => $group['subtotal'],
                        'commission_amount' => 0,
                    ]);

                    OrderStatusHistory::query()->create([
                        'vendor_order_id' => $vendorOrder->id,
                        'from_status' => null,
                        'to_status' => 'pending',
                        'changed_by' => $customerId,
                        'note' => 'Khách hàng đặt đơn COD',
                        'created_at' => now(),
                    ]);

                    $vendorCommissionTotal = 0;

                    foreach ($group['items'] as $line) {
                        $sku = $line['sku'];
                        $product = $line['product'];
                        $lineTotal = $line['line_total'];

                        $commission = $this->commissionService->calculate(
                            (int) $product->category_id,
                            (int) $lineTotal,
                        );

                        $orderItem = OrderItem::query()->create([
                            'vendor_order_id' => $vendorOrder->id,
                            'sku_id' => $sku->id,
                            'product_name' => $product->name,
                            'sku_code' => $sku->sku_code,
                            'unit_price' => $line['unit_price'],
                            'qty' => $line['qty'],
                            'line_total' => $lineTotal,
                            'commission_rate' => $commission->rate,
                            'commission_amount' => $commission->commissionAmount,
                        ]);

                        $this->reservationService->reserve(
                            $orderItem->id,
                            (int) $sku->id,
                            (int) $line['qty'],
                            $customerId,
                        );

                        $vendorCommissionTotal += $commission->commissionAmount;
                    }

                    $vendorOrder->update([
                        'commission_amount' => $vendorCommissionTotal,
                    ]);
                }

                Payment::query()->create([
                    'order_id' => $order->id,
                    'method' => 'cod',
                    'amount' => $orderSubtotal,
                    'status' => 'pending',
                    'txn_ref' => 'COD-'.$order->order_code,
                    'paid_at' => null,
                ]);

                CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->delete();

                return $order->load(['vendorOrders.items.allocations', 'payments']);
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            $raceWinner = Order::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('customer_id', $customerId)
                ->first();

            if ($raceWinner !== null) {
                return $raceWinner->load(['vendorOrders.items.allocations', 'payments']);
            }

            throw $e;
        }
    }

    private function generateUniqueOrderCode(): string
    {
        do {
            $code = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::query()->where('order_code', $code)->exists());

        return $code;
    }

    private function generateUniqueVendorOrderCode(): string
    {
        do {
            $code = 'VO-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (VendorOrder::query()->where('vo_code', $code)->exists());

        return $code;
    }
}
