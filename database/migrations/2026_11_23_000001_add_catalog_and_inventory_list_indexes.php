<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mixed directions match newest catalog ordering with its stable id ASC
        // tie-breaker. Supported by both MySQL 8 and the SQLite test runtime.
        DB::statement('CREATE INDEX idx_products_status_newest ON products (status, created_at DESC, id ASC)');
        Schema::table('skus', function (Blueprint $table) {
            $table->index(['is_active', 'product_id', 'price'], 'idx_skus_active_product_price');
        });
        Schema::table('batches', function (Blueprint $table) {
            $table->index(['status', 'exp_date', 'id'], 'idx_batches_status_expiry');
        });
        DB::statement('CREATE INDEX idx_vendor_orders_status_latest ON vendor_orders (vendor_id, status, id DESC)');
    }

    public function down(): void
    {
        Schema::table('vendor_orders', fn (Blueprint $table) => $table->dropIndex('idx_vendor_orders_status_latest'));
        Schema::table('batches', fn (Blueprint $table) => $table->dropIndex('idx_batches_status_expiry'));
        Schema::table('skus', fn (Blueprint $table) => $table->dropIndex('idx_skus_active_product_price'));
        Schema::table('products', fn (Blueprint $table) => $table->dropIndex('idx_products_status_newest'));
    }
};
