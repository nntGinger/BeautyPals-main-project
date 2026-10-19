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
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->decimal('rate', 5, 2);
            $table->date('effective_from');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('category_key')->default(0);
            $table->timestamps();

            $table->unique(['category_key', 'effective_from'], 'uq_commission_rules_scope');
            $table->index(['category_id', 'is_active', 'effective_from'], 'idx_commission_rules_lookup');
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('gross_sales')->default(0);
            $table->unsignedBigInteger('commission_total')->default(0);
            $table->unsignedBigInteger('refund_total')->default(0);
            $table->bigInteger('adjustment')->default(0);
            $table->bigInteger('net_payout')->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'period_start', 'period_end'], 'uq_settlements_vendor_period');
            $table->index('status', 'idx_settlements_status');
        });

        Schema::create('settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->foreignId('vendor_order_id')->unique('uq_settlement_lines_vendor_order')->constrained('vendor_orders');
            $table->unsignedBigInteger('gross');
            $table->unsignedBigInteger('commission');
            $table->unsignedBigInteger('refund')->default(0);
            $table->bigInteger('net');
            $table->timestamps();

            $table->index('settlement_id', 'idx_settlement_lines_settlement');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id'], 'idx_audit_entity');
            $table->index(['actor_id', 'created_at'], 'idx_audit_actor');
            $table->index(['action', 'created_at'], 'idx_audit_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settlement_lines');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('commission_rules');
    }
};
