<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Models\Batch;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Sku;
use Illuminate\Support\Facades\DB;

final class CartService
{
    private const MAX_QUANTITY = 4294967295;

    /**
     * Add an SKU to the user's cart.
     * Adding an existing SKU increases its quantity.
     */
    public function addItem(
        int $userId,
        int $skuId,
        int $quantity
    ): CartItem {
        $this->assertPositiveQuantity($quantity);

        return DB::transaction(function () use (
            $userId,
            $skuId,
            $quantity
        ): CartItem {
            $cart = Cart::query()->firstOrCreate([
                'user_id' => $userId,
            ]);

            // Serialize changes to this user's cart.
            $cart = Cart::query()
                ->whereKey($cart->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->findSellableSku($skuId);

            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('sku_id', $skuId)
                ->lockForUpdate()
                ->first();

            $currentQuantity = $item?->qty ?? 0;

            if (
                $quantity > self::MAX_QUANTITY - $currentQuantity
            ) {
                throw CartException::invalidQuantity();
            }

            $newQuantity = $currentQuantity + $quantity;

            $this->assertEnoughStock(
                $skuId,
                $newQuantity
            );

            if ($item === null) {
                return CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'sku_id' => $skuId,
                    'qty' => $newQuantity,
                ]);
            }

            $item->update([
                'qty' => $newQuantity,
            ]);

            return $item->refresh();
        }, 3);
    }

    /**
     * Replace the quantity of an existing cart item.
     */
    public function updateQuantity(
        int $userId,
        int $skuId,
        int $quantity
    ): CartItem {
        $this->assertPositiveQuantity($quantity);

        return DB::transaction(function () use (
            $userId,
            $skuId,
            $quantity
        ): CartItem {
            $cart = Cart::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($cart === null) {
                throw CartException::itemNotFound($skuId);
            }

            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('sku_id', $skuId)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                throw CartException::itemNotFound($skuId);
            }

            $this->findSellableSku($skuId);

            $this->assertEnoughStock(
                $skuId,
                $quantity
            );

            $item->update([
                'qty' => $quantity,
            ]);

            return $item->refresh();
        }, 3);
    }

    /**
     * Remove an SKU from the user's cart.
     */
    public function removeItem(
        int $userId,
        int $skuId
    ): void {
        DB::transaction(function () use (
            $userId,
            $skuId
        ): void {
            $cart = Cart::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($cart === null) {
                throw CartException::itemNotFound($skuId);
            }

            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('sku_id', $skuId)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                throw CartException::itemNotFound($skuId);
            }

            $item->delete();
        }, 3);
    }

    /**
     * Return cart items grouped by vendor.
     *
     * Prices are read from the current SKU records.
     */
    public function getCartGroupedByVendor(
        int $userId
    ): array {
        $cart = Cart::query()
            ->where('user_id', $userId)
            ->first();

        if ($cart === null) {
            return [
                'cart_id' => null,
                'vendors' => [],
                'grand_total' => 0,
                'total_quantity' => 0,
            ];
        }

        $items = $cart->items()
            ->with('sku.product.vendor')
            ->orderBy('id')
            ->get();

        $groups = [];
        $grandTotal = 0;
        $totalQuantity = 0;

        foreach ($items as $item) {
            $sku = $item->sku;
            $product = $sku->product;
            $vendor = $product->vendor;

            $vendorId = (int) $vendor->id;
            $unitPrice = (int) $sku->price;
            $quantity = (int) $item->qty;

            $lineTotal = $unitPrice * $quantity;

            if (! isset($groups[$vendorId])) {
                $groups[$vendorId] = [
                    'vendor_id' => $vendorId,
                    'vendor_name' => $vendor->store_name,
                    'subtotal' => 0,
                    'items' => [],
                ];
            }

            $groups[$vendorId]['items'][] = [
                'cart_item_id' => (int) $item->id,
                'sku_id' => (int) $sku->id,
                'sku_code' => $sku->sku_code,
                'product_id' => (int) $product->id,
                'product_name' => $product->name,
                'variant_name' => $sku->variant_name,
                'unit_price' => $unitPrice,
                'qty' => $quantity,
                'line_total' => $lineTotal,
            ];

            $groups[$vendorId]['subtotal'] += $lineTotal;

            $grandTotal += $lineTotal;
            $totalQuantity += $quantity;
        }

        return [
            'cart_id' => (int) $cart->id,
            'vendors' => array_values($groups),
            'grand_total' => $grandTotal,
            'total_quantity' => $totalQuantity,
        ];
    }

    /**
     * Ensure SKU, product and vendor are sellable.
     */
    private function findSellableSku(int $skuId): Sku
    {
        $sku = Sku::query()
            ->with('product.vendor')
            ->find($skuId);

        if ($sku === null) {
            throw CartException::skuNotFound($skuId);
        }

        if (! $sku->is_active) {
            throw CartException::skuInactive($skuId);
        }

        $product = $sku->product;

        if (
            $product === null
            || $product->status !== 'approved'
        ) {
            throw CartException::productUnavailable(
                (int) $sku->product_id
            );
        }

        $vendor = $product->vendor;

        if (
            $vendor === null
            || $vendor->status !== 'approved'
        ) {
            throw CartException::vendorUnavailable(
                (int) $product->vendor_id
            );
        }

        return $sku;
    }

    /**
     * Check stock across active, unexpired batches.
     *
     * Available = max(qty_on_hand - qty_reserved, 0)
     * summed across eligible batches.
     */
    private function assertEnoughStock(
        int $skuId,
        int $requested
    ): void {
        $available = (int) Batch::query()
            ->where('sku_id', $skuId)
            ->where('status', 'active')
            ->whereDate(
                'exp_date',
                '>=',
                now()->toDateString()
            )
            ->selectRaw(
                'COALESCE(SUM(
                    CASE
                        WHEN qty_on_hand > qty_reserved
                        THEN qty_on_hand - qty_reserved
                        ELSE 0
                    END
                ), 0) AS available'
            )
            ->value('available');

        if ($requested > $available) {
            throw CartException::insufficientStock(
                $skuId,
                $requested,
                $available
            );
        }
    }

    /**
     * Quantity must fit the cart_items.qty column.
     */
    private function assertPositiveQuantity(
        int $quantity
    ): void {
        if (
            $quantity < 1
            || $quantity > self::MAX_QUANTITY
        ) {
            throw CartException::invalidQuantity();
        }
    }
}
