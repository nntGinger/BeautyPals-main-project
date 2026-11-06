<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CommissionRuleController as AdminCommissionRuleController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductModerationController;
use App\Http\Controllers\Admin\SettlementController as AdminSettlementController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VendorModerationController as AdminVendorModerationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VendorRegisteredUserController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\Customer\AddressController as CustomerAddressController;
use App\Http\Controllers\Customer\CartController as CustomerCartController;
use App\Http\Controllers\Customer\CheckoutController as CustomerCheckoutController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\IngredientLookupController;
use App\Http\Controllers\Vendor\DashboardController as VendorDashboardController;
use App\Http\Controllers\Vendor\InventoryController as VendorInventoryController;
use App\Http\Controllers\Vendor\OrderController as VendorOrderController;
use App\Http\Controllers\Vendor\PendingController as VendorPendingController;
use App\Http\Controllers\Vendor\ProductController;
use App\Http\Controllers\Vendor\SettlementController as VendorSettlementController;
use App\Http\Controllers\Vendor\SubmitProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/products', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/products/{slugOrId}', [CatalogController::class, 'show'])->name('catalog.show');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/register/vendor', [VendorRegisteredUserController::class, 'create'])->name('register.vendor');
    Route::post('/register/vendor', [VendorRegisteredUserController::class, 'store'])->name('register.vendor.store');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/ingredients/lookup', IngredientLookupController::class)
    ->middleware(['auth', 'role:admin,vendor'])->name('ingredients.lookup');

Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/', CustomerDashboardController::class)->name('dashboard');
    Route::get('/dashboard', CustomerDashboardController::class);

    Route::get('/cart', [CustomerCartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CustomerCartController::class, 'store'])->name('cart.store');
    Route::put('/cart/{skuId}', [CustomerCartController::class, 'update'])->whereNumber('skuId')->name('cart.update');
    Route::delete('/cart/{skuId}', [CustomerCartController::class, 'destroy'])->whereNumber('skuId')->name('cart.destroy');

    Route::get('/addresses', [CustomerAddressController::class, 'index'])->name('addresses.index');
    Route::post('/addresses', [CustomerAddressController::class, 'store'])->name('addresses.store');
    Route::put('/addresses/{address}', [CustomerAddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
    Route::post('/addresses/{address}/default', [CustomerAddressController::class, 'setDefault'])->whereNumber('address')->name('addresses.default');
    Route::delete('/addresses/{address}', [CustomerAddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');

    Route::get('/checkout', [CustomerCheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CustomerCheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
    Route::post('/orders/{vendorOrder}/cancel', [CustomerOrderController::class, 'cancel'])->whereNumber('vendorOrder')->name('orders.cancel');
});

Route::middleware(['auth', 'role:vendor'])->group(function () {
    Route::get('/vendor/pending', VendorPendingController::class)->name('vendor.pending');
});

Route::middleware(['auth', 'role:vendor', 'vendor.approved'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/', VendorDashboardController::class)->name('dashboard');
    Route::get('/dashboard', VendorDashboardController::class);

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->whereNumber('product')->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->whereNumber('product')->name('products.destroy');
    Route::post('/products/{product}/submit', SubmitProductController::class)->whereNumber('product')->name('products.submit');
    Route::get('/products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');

    Route::get('/inventory', [VendorInventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/receive', [VendorInventoryController::class, 'receive'])->name('inventory.receive');
    Route::post('/inventory/{batch}/adjust', [VendorInventoryController::class, 'adjust'])->whereNumber('batch')->name('inventory.adjust');

    Route::get('/orders', [VendorOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{vendorOrder}', [VendorOrderController::class, 'show'])->whereNumber('vendorOrder')->name('orders.show');
    Route::post('/orders/{vendorOrder}/transition', [VendorOrderController::class, 'transition'])->whereNumber('vendorOrder')->name('orders.transition');

    Route::get('/settlements', [VendorSettlementController::class, 'index'])->name('settlements.index');
    Route::get('/settlements/{settlement}', [VendorSettlementController::class, 'show'])->whereNumber('settlement')->name('settlements.show');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/dashboard', AdminDashboardController::class);

    Route::resource('ingredients', IngredientController::class)->except('show');
    Route::resource('categories', AdminCategoryController::class)->except('show');
    Route::resource('brands', AdminBrandController::class)->except('show');

    Route::get('/products', [ProductModerationController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductModerationController::class, 'show'])->whereNumber('product')->name('products.show');
    foreach (['approve', 'reject', 'hide', 'restore'] as $decision) {
        Route::post('/products/{product}/'.$decision, [ProductModerationController::class, 'decide'])
            ->whereNumber('product')->defaults('decision', $decision)->name('products.'.$decision);
    }

    Route::get('/vendors', [AdminVendorModerationController::class, 'index'])->name('vendors.index');
    Route::get('/vendors/{vendor}', [AdminVendorModerationController::class, 'show'])->whereNumber('vendor')->name('vendors.show');
    Route::get('/vendors/{vendor}/license', [AdminVendorModerationController::class, 'license'])->whereNumber('vendor')->name('vendors.license');
    foreach (['approve', 'reject', 'suspend', 'restore'] as $decision) {
        Route::post('/vendors/{vendor}/'.$decision, [AdminVendorModerationController::class, 'decide'])
            ->whereNumber('vendor')->defaults('decision', $decision)->name('vendors.'.$decision);
    }

    Route::get('/commissions', [AdminCommissionRuleController::class, 'index'])->name('commissions.index');
    Route::post('/commissions', [AdminCommissionRuleController::class, 'store'])->name('commissions.store');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->whereNumber('user')->name('users.toggle-status');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{vendorOrder}', [AdminOrderController::class, 'show'])->whereNumber('vendorOrder')->name('orders.show');

    Route::get('/settlements', [AdminSettlementController::class, 'index'])->name('settlements.index');
    Route::post('/settlements/generate', [AdminSettlementController::class, 'generate'])->name('settlements.generate');
    Route::get('/settlements/{settlement}', [AdminSettlementController::class, 'show'])->whereNumber('settlement')->name('settlements.show');
    Route::post('/settlements/{settlement}/pay', [AdminSettlementController::class, 'markPaid'])->whereNumber('settlement')->name('settlements.pay');

    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
});
