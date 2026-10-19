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
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sku_id')->constrained('skus');
            $table->string('batch_no', 60);
            $table->date('mfg_date');
            $table->date('exp_date');
            $table->unsignedInteger('qty_received');
            $table->unsignedInteger('qty_on_hand');
            $table->unsignedInteger('qty_reserved')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['sku_id', 'batch_no'], 'uq_batches_sku_batch');
            $table->index(['sku_id', 'status', 'exp_date'], 'idx_batches_fefo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
