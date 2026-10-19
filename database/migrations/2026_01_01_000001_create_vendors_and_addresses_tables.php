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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->unique('uq_vendors_owner')->constrained('users');
            $table->string('store_name', 150)->unique('uq_vendors_store_name');
            $table->string('tax_code', 20)->unique('uq_vendors_tax_code');
            $table->string('phone', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('license_file', 255);
            $table->string('status', 20)->default('pending');
            $table->string('reject_reason', 255)->nullable();
            $table->string('suspended_reason', 255)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_vendors_status');
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('receiver', 120);
            $table->string('phone', 20);
            $table->string('line', 255);
            $table->string('ward', 100);
            $table->string('district', 100);
            $table->string('city', 100);
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('default_user_id')->nullable();
            $table->timestamps();

            $table->unique('default_user_id', 'uq_addresses_one_default');
            $table->index('user_id', 'idx_addresses_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('vendors');
    }
};
