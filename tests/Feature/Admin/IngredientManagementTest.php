<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_delete_ingredient_and_aliases(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.ingredients.store'), [
            'inci_name' => ' Niacinamide ', 'display_name' => 'Vitamin B3',
            'function' => '', 'aliases' => [' Nicotinamide ', 'Vitamin B3'],
        ])->assertRedirect(route('admin.ingredients.index'));

        $ingredient = Ingredient::where('inci_name', 'Niacinamide')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Nicotinamide', 'Vitamin B3'], $ingredient->aliases->pluck('alias')->all());
        $this->get(route('admin.ingredients.index', ['q' => 'Nicotinamide']))->assertOk()->assertSee('Niacinamide');
        $this->put(route('admin.ingredients.update', $ingredient), [
            'inci_name' => 'Niacinamide', 'display_name' => 'Vitamin B3', 'aliases' => ['Vitamin B3', 'B3'],
        ])->assertRedirect(route('admin.ingredients.edit', $ingredient));
        $this->assertDatabaseMissing('ingredient_aliases', ['alias' => 'Nicotinamide']);
        $this->assertDatabaseHas('ingredient_aliases', ['alias' => 'B3', 'ingredient_id' => $ingredient->id]);
        $this->delete(route('admin.ingredients.destroy', $ingredient))->assertRedirect(route('admin.ingredients.index'));
        $this->assertDatabaseMissing('ingredients', ['id' => $ingredient->id]);
    }

    public function test_validation_rejects_duplicates_and_preserves_existing_data(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $other = Ingredient::create(['inci_name' => 'Retinol', 'display_name' => 'Vitamin A']);
        $other->aliases()->create(['alias' => 'Retinoid']);
        $ingredient = Ingredient::create(['inci_name' => 'Niacinamide', 'display_name' => 'Vitamin B3']);

        $this->from(route('admin.ingredients.edit', $ingredient))->put(route('admin.ingredients.update', $ingredient), [
            'inci_name' => 'Changed', 'display_name' => 'Vitamin B3', 'aliases' => ['Nicotinamide', 'nicotinamide'],
        ])->assertSessionHasErrors('aliases.1');
        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'inci_name' => 'Niacinamide']);
        $this->post(route('admin.ingredients.store'), [
            'inci_name' => 'New', 'display_name' => 'Retinoid', 'aliases' => [],
        ])->assertSessionHasErrors('display_name');
        $this->post(route('admin.ingredients.store'), [
            'inci_name' => 'New', 'display_name' => 'New', 'aliases' => ['Retinol'],
        ])->assertSessionHasErrors('aliases.0');
    }

    public function test_used_ingredient_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $ingredient = Ingredient::create(['inci_name' => 'Glycerin', 'display_name' => 'Glycerin']);
        $category = Category::create(['name' => 'Chăm sóc da', 'slug' => 'cham-soc-da']);
        $vendor = Vendor::factory()->approved()->create();
        $product = Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Sản phẩm thử', 'slug' => 'san-pham-thu', 'status' => 'draft',
        ]);
        $product->ingredients()->attach($ingredient->id, ['position' => 1]);
        $this->from(route('admin.ingredients.index'))->delete(route('admin.ingredients.destroy', $ingredient))
            ->assertRedirect(route('admin.ingredients.index'))->assertSessionHas('error');
        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id]);
    }

    public function test_customer_and_vendor_cannot_manage_ingredients(): void
    {
        $ingredient = Ingredient::create(['inci_name' => 'Glycerin', 'display_name' => 'Glycerin']);
        $this->get(route('admin.ingredients.index'))->assertRedirect(route('login'));
        foreach (['customer', 'vendor'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('admin.ingredients.index'))->assertForbidden();
            $this->post(route('admin.ingredients.store'), ['inci_name' => 'A', 'display_name' => 'A'])->assertForbidden();
            $this->put(route('admin.ingredients.update', $ingredient), ['inci_name' => 'A', 'display_name' => 'A'])->assertForbidden();
            $this->delete(route('admin.ingredients.destroy', $ingredient))->assertForbidden();
        }
    }
}
