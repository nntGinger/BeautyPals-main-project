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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique('uq_brands_name');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 150)->unique('uq_categories_slug');
            $table->timestamps();

            $table->index('parent_id', 'idx_categories_parent');
        });

        Schema::create('skin_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique('uq_skin_types_name');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('name', 200);
            $table->string('slug', 220)->unique('uq_products_slug');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('reject_reason', 255)->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status'], 'idx_products_vendor_status');
            $table->index(['category_id', 'status'], 'idx_products_category_status');
            $table->index('brand_id', 'idx_products_brand');
            $table->index('name', 'idx_products_name');
        });

        Schema::create('product_skin_type', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('skin_type_id')->constrained('skin_types')->cascadeOnDelete();
            $table->primary(['product_id', 'skin_type_id']);
            $table->index('skin_type_id', 'idx_pst_skin_type');
        });

        Schema::create('skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku_code', 60)->unique('uq_skus_code');
            $table->string('variant_name', 100);
            $table->unsignedBigInteger('price');
            $table->string('size', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active'], 'idx_skus_product_active');
            $table->index('price', 'idx_skus_price');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path', 255);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort'], 'idx_product_images_product_sort');
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('inci_name', 150)->unique('uq_ingredients_inci');
            $table->string('display_name', 150);
            $table->string('function', 255)->nullable();
            $table->timestamps();

            $table->index('display_name', 'idx_ingredients_display');
        });

        Schema::create('ingredient_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->string('alias', 150)->unique('uq_ingredient_aliases_alias');
            $table->timestamps();

            $table->index('ingredient_id', 'idx_ingredient_aliases_ingredient');
        });

        Schema::create('product_ingredients', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients');
            $table->unsignedSmallInteger('position');
            $table->decimal('concentration', 5, 2)->nullable();
            $table->primary(['product_id', 'ingredient_id']);
            $table->unique(['product_id', 'position'], 'uq_product_ingredients_position');
            $table->index('ingredient_id', 'idx_product_ingredients_ingredient');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_ingredients');
        Schema::dropIfExists('ingredient_aliases');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('skus');
        Schema::dropIfExists('product_skin_type');
        Schema::dropIfExists('products');
        Schema::dropIfExists('skin_types');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
