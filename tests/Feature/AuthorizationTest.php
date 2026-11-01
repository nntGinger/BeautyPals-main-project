<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_must_login_and_each_role_is_isolated(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->seed();
        foreach (['customer', 'vendor', 'admin'] as $role) {
            $this->actingAs(User::where('role', $role)->where('status', 'active')->firstOrFail());
            foreach (['customer', 'vendor', 'admin'] as $area) {
                $response = $this->get("/$area");
                $role === $area ? $response->assertOk() : $response->assertForbidden()->assertSee('Bạn không được phép');
                $dashboard = $this->get("/$area/dashboard");
                $role === $area ? $dashboard->assertOk() : $dashboard->assertForbidden();
            }
        }
    }

    public function test_unapproved_or_missing_vendor_cannot_enter_vendor_area(): void
    {
        foreach (['pending', 'rejected', 'suspended'] as $status) {
            $vendor = Vendor::factory()->create(['status' => $status]);
            $this->actingAs($vendor->owner)->get('/vendor')->assertForbidden();
            $this->get('/vendor/pending')->assertOk();
        }
        $this->actingAs(User::factory()->create(['role' => 'vendor']))->get('/vendor')->assertForbidden();
    }

    public function test_role_alias_supports_pipe_separated_roles(): void
    {
        Route::middleware(['web', 'auth', 'role:customer|admin'])->get('/role-test', fn () => 'OK');
        $this->actingAs(User::factory()->create())->get('/role-test')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'vendor']))->get('/role-test')->assertForbidden();
    }

    public function test_product_and_vendor_order_policies_enforce_ownership_and_approval(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $other = Vendor::factory()->approved()->create();
        $product = new Product(['vendor_id' => $vendor->id, 'status' => 'draft']);
        $order = new VendorOrder(['vendor_id' => $vendor->id]);
        $this->assertTrue(Gate::forUser($vendor->owner)->allows('update', $product));
        $this->assertFalse(Gate::forUser($other->owner)->allows('update', $product));
        $this->assertTrue(Gate::forUser($vendor->owner)->allows('view', $order));
        $this->assertFalse(Gate::forUser($other->owner)->allows('view', $order));
        $vendor->update(['status' => 'suspended']);
        $this->assertFalse(Gate::forUser($vendor->owner->fresh())->allows('update', $product));
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'locked']);
        $this->assertFalse(Gate::forUser($admin)->allows('update', $product));
    }
}
