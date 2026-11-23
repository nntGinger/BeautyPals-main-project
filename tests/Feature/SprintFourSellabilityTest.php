<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Inventory\InventoryException;
use App\Support\InventoryDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SprintFourSellabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-22 18:00:00', 'UTC'));
        $this->customer = User::factory()->customer()->create();
        $this->address = Address::create([
            'user_id' => $this->customer->id, 'receiver' => 'Customer', 'phone' => '0900000000',
            'line' => '123 Test', 'ward' => 'Ward', 'district' => 'District', 'city' => 'HCM',
        ]);
        CommissionRule::create(['category_id' => null, 'rate' => '10.00', 'effective_from' => '2026-01-01', 'is_active' => true]);
        $this->actingAs($this->customer);
    }

    private function sku(string $expiry): Sku
    {
        $sku = Sku::factory()->for(Product::factory()->approved())->create(['price' => 100000]);
        $sku->batches()->create([
            'batch_no' => 'S4-TEST', 'mfg_date' => '2026-01-01', 'exp_date' => $expiry,
            'qty_received' => 10, 'qty_on_hand' => 10, 'qty_reserved' => 0, 'status' => 'active',
        ]);

        return $sku;
    }

    public static function expiryCases(): array
    {
        return [
            'yesterday' => ['2026-11-22', false],
            'today in Vietnam' => ['2026-11-23', false],
            'tomorrow' => ['2026-11-24', true],
        ];
    }

    #[DataProvider('expiryCases')]
    public function test_catalog_cart_and_checkout_agree_before_expiry_job(string $expiry, bool $sellable): void
    {
        $sku = $this->sku($expiry);
        $this->assertSame('2026-11-23', InventoryDate::today());
        $this->assertSame('2026-11-22', today()->toDateString());
        $this->getJson(route('catalog.uc04.search', ['q' => $sku->product->slug]))
            ->assertOk()->assertJsonPath('data.0.in_stock', $sellable ? 1 : 0);
        $this->getJson(route('catalog.uc04.search', ['q' => $sku->product->slug, 'in_stock' => 1]))
            ->assertOk()->assertJsonCount($sellable ? 1 : 0, 'data');
        $response = $this->postJson(route('customer.cart.store'), ['sku_id' => $sku->id, 'qty' => 1]);

        if ($sellable) {
            $response->assertRedirect();
            $order = app(CheckoutService::class)->place($this->customer->id, $this->address->id, (string) Str::uuid());
            $this->assertSame(1, $order->vendorOrders->first()->items->first()->allocations->sum('qty'));
            $this->assertSame(1, $sku->batches()->first()->qty_reserved);
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('sku_id');
            // Checkout must independently reject a stale cart populated earlier.
            $cart = Cart::firstOrCreate(['user_id' => $this->customer->id]);
            $cart->items()->create(['sku_id' => $sku->id, 'qty' => 1]);
            $this->putJson(route('customer.cart.update', $sku->id), ['qty' => 2])->assertUnprocessable();
            try {
                app(CheckoutService::class)->place($this->customer->id, $this->address->id, (string) Str::uuid());
                $this->fail('Expired stock must not be reserved.');
            } catch (InventoryException) {
                $this->assertDatabaseCount('orders', 0);
                $this->assertDatabaseCount('order_item_allocations', 0);
                $this->assertSame(0, $sku->batches()->first()->qty_reserved);
            }
        }
        $this->assertSame('active', $sku->batches()->first()->status);
    }

    public function test_locked_vendor_is_hidden_and_existing_cart_cannot_update_or_checkout_then_unlock_restores_sale(): void
    {
        $sku = $this->sku('2026-11-24');
        $owner = $sku->product->vendor->owner;
        app(CartService::class)->addItem($this->customer->id, $sku->id, 1);
        $owner->update(['status' => 'locked']);

        foreach ([[], ['in_stock' => 1]] as $filter) {
            $this->getJson(route('catalog.uc04.search', ['q' => $sku->product->slug] + $filter))
                ->assertOk()->assertJsonCount(0, 'data');
        }
        $this->postJson(route('customer.cart.store'), ['sku_id' => $sku->id, 'qty' => 1])->assertUnprocessable();
        $this->putJson(route('customer.cart.update', $sku->id), ['qty' => 2])->assertUnprocessable();
        $this->postJson(route('customer.checkout.store'), ['address_id' => $this->address->id, 'idempotency_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $sku->batches()->first()->qty_reserved);

        $owner->update(['status' => 'active']);
        $this->getJson(route('catalog.uc04.search', ['q' => $sku->product->slug, 'in_stock' => 1]))->assertJsonCount(1, 'data');
        $this->putJson(route('customer.cart.update', $sku->id), ['qty' => 2])->assertRedirect();
        $this->post(route('customer.checkout.store'), ['address_id' => $this->address->id, 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, $sku->batches()->first()->qty_reserved);
    }

    public function test_midnight_boundary_and_expiry_command_use_same_vietnam_date(): void
    {
        $sku = $this->sku('2026-11-23');
        $this->travelTo(Carbon::parse('2026-11-22 16:59:59', 'UTC'));
        $this->getJson(route('catalog.uc04.search', ['q' => $sku->product->slug, 'in_stock' => 1]))->assertJsonCount(1, 'data');
        app(CartService::class)->addItem($this->customer->id, $sku->id, 1);

        $this->travelTo(Carbon::parse('2026-11-22 17:00:00', 'UTC'));
        $this->getJson(route('catalog.uc04.search', ['q' => $sku->product->slug, 'in_stock' => 1]))->assertJsonCount(0, 'data');
        $this->putJson(route('customer.cart.update', $sku->id), ['qty' => 2])->assertUnprocessable();
        $this->postJson(route('customer.checkout.store'), ['address_id' => $this->address->id, 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->artisan('batches:expire')->expectsOutput('Đã đánh dấu 1 lô hết hạn.')->assertSuccessful();
        $this->assertSame('expired', $sku->batches()->first()->status);
        $this->assertSame(0, $sku->batches()->first()->qty_reserved);
        $this->artisan('batches:expire')->expectsOutput('Đã đánh dấu 0 lô hết hạn.')->assertSuccessful();
    }
}
