<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique('uq_carts_user')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained('skus')->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->timestamps();

            $table->unique(['cart_id', 'sku_id'], 'uq_cart_items_cart_sku');
            $table->index('sku_id', 'idx_cart_items_sku');
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique('uq_vouchers_code');
            $table->string('type', 10);
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedBigInteger('min_order')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at'], 'idx_vouchers_active');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 30)->unique('uq_orders_code');
            $table->foreignId('customer_id')->constrained('users');
            $table->string('receiver', 120);
            $table->string('phone', 20);
            $table->string('address_text', 500);
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('grand_total');
            $table->string('payment_method', 20)->default('cod');
            $table->string('payment_status', 20)->default('pending');
            $table->string('status', 20)->default('pending');
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->char('idempotency_key', 36)->unique('uq_orders_idempotency');
            $table->timestamp('placed_at')->useCurrent();
            $table->timestamps();

            $table->index(['customer_id', 'placed_at'], 'idx_orders_customer_placed');
            $table->index('status', 'idx_orders_status');
        });

        Schema::create('vendor_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('vo_code', 30)->unique('uq_vendor_orders_code');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->string('tracking_code', 60)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'vendor_id'], 'uq_vendor_orders_order_vendor');
            $table->index(['vendor_id', 'status', 'created_at'], 'idx_vendor_orders_vendor_status');
            $table->index(['status', 'delivered_at'], 'idx_vendor_orders_autocomplete');
            $table->index(['vendor_id', 'status', 'completed_at'], 'idx_vendor_orders_settlement');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_order_id')->constrained('vendor_orders');
            $table->foreignId('sku_id')->constrained('skus');
            $table->string('product_name', 200);
            $table->string('sku_code', 60);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('line_total');
            $table->decimal('commission_rate', 5, 2);
            $table->unsignedBigInteger('commission_amount');
            $table->timestamps();

            $table->index('vendor_order_id', 'idx_order_items_vendor_order');
            $table->index('sku_id', 'idx_order_items_sku');
        });

        Schema::create('order_item_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items');
            $table->foreignId('batch_id')->constrained('batches');
            $table->unsignedInteger('qty');
            $table->string('status', 20)->default('held');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['order_item_id', 'batch_id'], 'uq_allocations_item_batch');
            $table->index(['batch_id', 'status'], 'idx_allocations_batch_status');
            $table->index(['status', 'expires_at'], 'idx_allocations_expiry');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches');
            $table->string('type', 20);
            $table->integer('qty');
            $table->string('ref_type', 40)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['batch_id', 'created_at'], 'idx_stock_movements_batch');
            $table->index(['ref_type', 'ref_id'], 'idx_stock_movements_ref');
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_order_id')->constrained('vendor_orders');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vendor_order_id', 'created_at'], 'idx_osh_vendor_order');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->string('method', 20)->default('cod');
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->string('txn_ref', 100)->nullable()->unique('uq_payments_txn_ref');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('order_id', 'idx_payments_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('order_item_allocations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('vendor_orders');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
