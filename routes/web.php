<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\VendorController as AdminVendorController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Vendor\DashboardController as VendorDashboardController;
use App\Http\Controllers\Vendor\PendingController as VendorPendingController;
use App\Http\Controllers\Vendor\RegistrationController as VendorRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    // A-12: Vendor registration (new user + vendor in one step, guest only)
    Route::get('/vendor/register', [VendorRegistrationController::class, 'create'])->name('vendor.register.form');
    Route::post('/vendor/register', [VendorRegistrationController::class, 'store'])->name('vendor.register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/', CustomerDashboardController::class)->name('dashboard');
    Route::get('/dashboard', CustomerDashboardController::class);
});

Route::middleware(['auth', 'role:vendor'])->group(function () {
    Route::get('/vendor/pending', VendorPendingController::class)->name('vendor.pending');
    // A-12: Vendor self-resubmit after rejection
    Route::post('/vendor/resubmit', [VendorRegistrationController::class, 'resubmit'])->name('vendor.resubmit');
});

Route::middleware(['auth', 'role:vendor', 'vendor.approved'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/', VendorDashboardController::class)->name('dashboard');
    Route::get('/dashboard', VendorDashboardController::class);
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/dashboard', AdminDashboardController::class);

    // A-12: Vendor management
    Route::prefix('vendors')->name('vendors.')->group(function () {
        Route::get('/', [AdminVendorController::class, 'index'])->name('index');
        Route::get('/{vendor}', [AdminVendorController::class, 'show'])->name('show');
        Route::post('/{vendor}/status', [AdminVendorController::class, 'updateStatus'])->name('status');
        Route::get('/{vendor}/license', [AdminVendorController::class, 'downloadLicense'])->name('license');
    });
});
