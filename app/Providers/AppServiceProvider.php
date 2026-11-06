<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Vendor;
use App\Observers\AuditableObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(
            fn (User $user) => $user->isLocked() ? false : null,
        );

        Vendor::observe(AuditableObserver::class);
        Product::observe(AuditableObserver::class);
        Category::observe(AuditableObserver::class);
        Brand::observe(AuditableObserver::class);
        CommissionRule::observe(AuditableObserver::class);
        Settlement::observe(AuditableObserver::class);
        User::observe(AuditableObserver::class);
    }
}
