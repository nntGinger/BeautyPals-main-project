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
        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('order_id')->constrained('orders');
            $table->unsignedBigInteger('amount');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['voucher_id', 'order_id'], 'uq_voucher_redemptions_order');
            $table->index(['user_id', 'voucher_id'], 'idx_voucher_redemptions_user');
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique('uq_reviews_order_item')->constrained('order_items');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('customer_id')->constrained('users');
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('visible');
            $table->timestamps();

            $table->index(['product_id', 'status'], 'idx_reviews_product');
            $table->index('customer_id', 'idx_reviews_customer');
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'product_id']);
            $table->index('product_id', 'idx_wishlists_product');
        });

        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_order_id')->constrained('vendor_orders');
            $table->foreignId('customer_id')->constrained('users');
            $table->string('reason', 500);
            $table->string('status', 20)->default('requested');
            $table->unsignedBigInteger('refund_amount')->default(0);
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index('vendor_order_id', 'idx_return_requests_vendor_order');
            $table->index(['status', 'created_at'], 'idx_return_requests_status');
            $table->index('customer_id', 'idx_return_requests_customer');
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items');
            $table->foreignId('batch_id')->nullable()->constrained('batches');
            $table->unsignedInteger('qty');
            $table->boolean('restock')->default(false);
            $table->timestamps();

            $table->unique(['return_request_id', 'order_item_id'], 'uq_return_items_request_item');
            $table->index('order_item_id', 'idx_return_items_order_item');
            $table->index('batch_id', 'idx_return_items_batch');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 60);
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'read_at', 'created_at'], 'idx_notifications_user_unread');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('voucher_redemptions');
    }
};
