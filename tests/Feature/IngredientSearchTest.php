<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_finds_inci_display_and_alias_without_duplicates_or_extra_fields(): void
    {
        $ingredient = Ingredient::create(['inci_name' => 'Niacinamide', 'display_name' => 'Vitamin B3', 'function' => 'Dưỡng da']);
        $ingredient->aliases()->createMany([['alias' => 'Nicotinamide'], ['alias' => 'B3']]);
        $this->actingAs(User::factory()->admin()->create());
        foreach (['Niacinamide', 'Vitamin B3', 'Nicotinamide', 'B3'] as $q) {
            $this->getJson(route('ingredients.lookup', ['q' => $q]))->assertOk()
                ->assertExactJson(['data' => [['id' => $ingredient->id, 'inci_name' => 'Niacinamide', 'display_name' => 'Vitamin B3']]]);
        }
        $this->getJson(route('ingredients.lookup', ['q' => '']))->assertExactJson(['data' => []]);
    }

    public function test_search_treats_like_wildcards_as_literal_and_keeps_other_filters(): void
    {
        $first = Ingredient::create(['inci_name' => 'Alpha_1', 'display_name' => 'A']);
        Ingredient::create(['inci_name' => 'AlphaX1', 'display_name' => 'B']);
        Ingredient::create(['inci_name' => 'Caffeine 5%', 'display_name' => 'C']);
        $this->assertEquals([$first->id], Ingredient::whereKey($first->id)->search('_')->pluck('id')->all());
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson(route('ingredients.lookup', ['q' => '_']))->assertJsonCount(1, 'data');
        $this->getJson(route('ingredients.lookup', ['q' => '%']))->assertJsonCount(1, 'data');
        foreach (['Vitamin!B', 'Vitamin\\B'] as $name) {
            $literal = Ingredient::create(['inci_name' => $name, 'display_name' => $name]);
            $this->assertSame([$literal->id], Ingredient::search($name)->pluck('id')->all());
        }
    }

    public function test_admin_index_paginates_and_escapes_names(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        for ($index = 0; $index < 21; $index++) {
            Ingredient::create(['inci_name' => "INCI$index", 'display_name' => "Tên thử $index"]);
        }
        Ingredient::create(['inci_name' => 'HTML', 'display_name' => '<script>alert(1)</script>']);
        $this->get(route('admin.ingredients.index'))
            ->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('page=2');
        $this->get(route('admin.ingredients.create'))->assertOk()->assertSee('Tên nguyên liệu');
    }

    public function test_only_admin_and_approved_vendor_can_lookup(): void
    {
        $this->get(route('ingredients.lookup'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->customer()->create())->getJson(route('ingredients.lookup'))->assertForbidden();
        $this->actingAs(Vendor::factory()->pending()->create()->owner)->getJson(route('ingredients.lookup'))->assertForbidden();
        $this->actingAs(Vendor::factory()->approved()->create()->owner)->getJson(route('ingredients.lookup'))->assertOk();
    }
}
