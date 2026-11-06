<?php

namespace App\Services\Catalog;

use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductIngredientService
{
    // Called inside ProductService's transaction, after locking the product.
    public function save(Product $product, array $rows): void
    {
        $ids = array_map(fn ($row) => (int) $row['ingredient_id'], $rows);
        $existing = DB::table('product_ingredients')->where('product_id', $product->id)
            ->lockForUpdate()->get()->keyBy('ingredient_id');
        $ingredients = Ingredient::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        if ($ingredients->count() !== count($ids)) {
            throw ValidationException::withMessages(['ingredients' => 'Danh mục thành phần vừa thay đổi. Vui lòng tải lại và chọn lại thành phần.']);
        }
        // Rebuild atomically to avoid UNIQUE(product_id, position) collisions when swapping positions.
        // Preserve concentration on retained links; never read concentration from the client.
        $product->ingredients()->detach();
        $pivot = [];
        foreach ($rows as $row) {
            $id = (int) $row['ingredient_id'];
            $pivot[$id] = ['position' => (int) $row['position'], 'concentration' => $existing->get($id)?->concentration];
        }
        if ($pivot !== []) {
            $product->ingredients()->attach($pivot);
        }
    }
}
